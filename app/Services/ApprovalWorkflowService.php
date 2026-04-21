<?php

namespace App\Services;

use App\Repositories\LeaveRequestRepository;
use App\Events\LeaveRequestStatusUpdated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApprovalWorkflowService
{
    private $repository;
    private $attendanceApiUrl;
    private $employeeApiUrl;

    public function __construct(LeaveRequestRepository $repository)
    {
        $this->repository = $repository;
        // Fetch API URL from config/services.php
        $this->attendanceApiUrl = rtrim(config('services.attendance.url'), '/');
        $this->employeeApiUrl = rtrim(config('services.employee.url'), '/');
    }

    /**
     * Fetch leave balance from Employee Module
     */
    public function getEmployeeLeaveBalance($employeeId, $token = null)
    {
        try {
            $empResp = Http::withToken($token)->get("{$this->employeeApiUrl}/employees/{$employeeId}/hierarchy");
            if ($empResp->successful()) {
                return $empResp->json('data.employee.leave_balance') ?? 0;
            }
        } catch (\Exception $e) {
            Log::error("Failed fetching employee balance: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Submit a new leave request and initiate the workflow
     */
    public function submitLeaveRequest($employeeId, $data, $token = null)
    {
        // 0. Cek Saldo Cuti di Modul Employee
        $leaveBalances = $this->getEmployeeLeaveBalance($employeeId, $token);

        // Cek apakah karyawan masih memiliki pengajuan cuti yang statusnya pending
        $hasPending = \App\Models\LeaveRequest::where('employee_id', $employeeId)
                        ->where('status', 'pending')
                        ->exists();

        if ($hasPending) {
            return [
                'status' => 'rejected',
                'leaves_balances' => $leaveBalances,
                'message' => "Pengajuan cuti ditolak. Anda masih memiliki pengajuan cuti yang berstatus pending."
            ];
        }

        $start = new \DateTime($data['start_date']);
        $end = new \DateTime($data['end_date']);
        $daysRequested = $start->diff($end)->days + 1;

        if ($leaveBalances < $daysRequested) {
            return [
                'status' => 'rejected',
                'leaves_balances' => $leaveBalances,
                'message' => "Pengajuan cuti ditolak. Saldo cuti ({$leaveBalances}) tidak mencukupi untuk {$daysRequested} hari."
            ];
        }

        // 1. Create the base Leave Request
        $leaveRequest = $this->repository->createRequest([
            'employee_id' => $employeeId,
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'reason'      => $data['reason'],
            'type'        => $data['type'],
            'status'      => 'pending'
        ]);

        // 2. Fetch the hierarchy to find the employee's manager
        $hierarchy = $this->fetchEmployeeHierarchy($employeeId, $token);

        if (empty($hierarchy)) {
            // Do NOT auto-approve if hierarchy fetch fails. It means API connection failed or data is missing.
            throw new \Exception("Gagal mengambil data hierarki karyawan. Permintaan cuti dibatalkan.");
        }

        $employeeData = $hierarchy['employee'];

        // Jika Position CEO, maka Auto-Approved
        if (strtolower($employeeData['position']) === 'ceo') {
             $this->finalizeApproval($leaveRequest->id);
             return clone $this->repository->getRequestById($leaveRequest->id);
        }

        // Jika manager tidak null
        if (array_key_exists('manager', $hierarchy) && $hierarchy['manager'] !== null) {
            $managerId = $hierarchy['manager']['id'];
            $managerName = $hierarchy['manager']['name'];

            // 3. Create the first level approval record
            $this->repository->createApprovalRecord([
                'leave_request_id' => $leaveRequest->id,
                'approver_id'      => $managerId,
                'status'           => 'pending',
                'approval_level'   => 1
            ]);

            // Add second level if manager also has a manager
            $level2ApproverId = $hierarchy['manager']['manager_id'] ?? null;
            if ($level2ApproverId) {
                // Determine level 2 manager's name by fetching their hierarchy (or fetching profile, let's fetch hierarchy for simplicity)
                $level2Hierarchy = $this->fetchEmployeeHierarchy($managerId, $token);

                $this->repository->createApprovalRecord([
                    'leave_request_id' => $leaveRequest->id,
                    'approver_id'      => $level2ApproverId,
                    'status'           => 'pending',
                    'approval_level'   => 2
                ]);

                // Store temporarily the level 2 info to append name
                $level2Name = $level2Hierarchy['manager']['name'] ?? 'Unknown';
            }
        } else {
            $this->finalizeApproval($leaveRequest->id);
        }

        $result = clone $this->repository->getRequestById($leaveRequest->id);
        $result->setAttribute('employee_name', $employeeData['name'] ?? 'Unknown');

        if (!empty($hierarchy['manager'])) {
            foreach ($result->approvals as $approval) {
                if ($approval->approval_level === 1) {
                    $approval->setAttribute('approver_name', $hierarchy['manager']['name'] ?? 'Unknown');
                } else if ($approval->approval_level === 2 && isset($level2Name)) {
                    $approval->setAttribute('approver_name', $level2Name);
                }
            }
        }

        $result->setAttribute('leaves_balances', $leaveBalances);
        return $result;
    }

    /**
     * Process an incoming approval decision untuk tingkatan manapun
     */
    public function processApproval($leaveRequest, $approverId, $status, $notes, $expectedLevel)
    {
        $approvalRecord = \App\Models\LeaveApproval::where('leave_request_id', $leaveRequest->id)
                            ->where('approver_id', $approverId)
                            ->where('status', 'pending')
                            ->firstOrFail();

        // Memastikan endpoint level disamakan dengan entitas riwayat level
        if ($approvalRecord->approval_level !== $expectedLevel) {
            throw new \Exception("Approval level mismatch. Endpoint is for level {$expectedLevel}, but record is level {$approvalRecord->approval_level}.");
        }

        // 1. Update the actual approval record
        $approvalRecord = $this->repository->updateApprovalRecord($approvalRecord->id, $status, $notes);

        if ($status === 'rejected') {
            // Immediately reject the entire request
            $updatedRequest = $this->repository->updateStatus($leaveRequest->id, 'rejected');

            // "Jika level 1 menolak cuti maka status approval level 2 otomatis tidak disetujui/ditolak"
            if ($expectedLevel === 1) {
                // Cek siapa pemohon cuti (untuk tau apakah ia 'manager' yang butuh level 2 atau bukan)
                $hierarchy = $this->fetchEmployeeHierarchy($leaveRequest->employee_id);
                $position = strtolower($hierarchy['employee']['position'] ?? '');

                if ($position === 'manager') {
                    // Cari tau CEO/Atasan Level 2 nya
                    $approverHierarchy = $this->fetchEmployeeHierarchy($approverId);
                    if ($approverHierarchy && array_key_exists('manager', $approverHierarchy) && $approverHierarchy['manager'] !== null) {
                        $level2ApproverId = $approverHierarchy['manager']['id'];
                        // Otomatis buatkan record "rejected" untuk Level 2
                        $this->repository->createApprovalRecord([
                            'leave_request_id' => $leaveRequest->id,
                            'approver_id'      => $level2ApproverId,
                            'status'           => 'rejected',
                            'notes'            => 'Auto-rejected because Level 1 was rejected.',
                            'approval_level'   => 2
                        ]);
                    }
                }
            }

            event(new LeaveRequestStatusUpdated($updatedRequest));
            return $updatedRequest;
        }

        // Jika disetujui, dan kita berada di Level 1
        if ($expectedLevel === 1) {
            $hierarchy = $this->fetchEmployeeHierarchy($leaveRequest->employee_id);
            $position = strtolower($hierarchy['employee']['position'] ?? '');

            // Sesuai rules: Jika posisi "Manager", maka masih butuh Level 2 (Direktur -> kemudian CEO)
            // Note: Since we pre-created Level 2 approval during submitLeaveRequest, we do not need to create it here.
            // We just need to make sure we don't finalize the request if Level 2 is still pending.

            $hasPendingLevel2 = \App\Models\LeaveApproval::where('leave_request_id', $leaveRequest->id)
                                ->where('approval_level', 2)
                                ->where('status', 'pending')
                                ->exists();

            if ($hasPendingLevel2) {
                return $this->repository->getRequestById($leaveRequest->id);
            }
        }

        // Finalisasi jika bukan manager (berarti Staff/Director) atau ini persetujuan di Level 2
        $updatedRequest = $this->finalizeApproval($leaveRequest->id);

        // Memanggil API Modul Employee untuk memotong saldo cuti (leave_balance)
        $start = new \DateTime($leaveRequest->start_date);
        $end = new \DateTime($leaveRequest->end_date);
        $daysRequested = $start->diff($end)->days + 1;

        try {
            $token = request()->bearerToken(); // atau oper token dari argumen

            // Ambil balance saat ini dulu dari Modul Employee
            $empResp = Http::withToken($token)->get("{$this->employeeApiUrl}/employees/{$leaveRequest->employee_id}/hierarchy");
            $currentBalance = 0;
            if ($empResp->successful()) {
                $currentBalance = $empResp->json('data.employee.leave_balance') ?? 0;
            }

            $newBalance = max(0, $currentBalance - $daysRequested);

            Log::info("Calling Employee Module to deduct balance for Employee: {$leaveRequest->employee_id}. Old: {$currentBalance}, New: {$newBalance}");

            $updateResp = Http::withToken($token)->put("{$this->employeeApiUrl}/employees/{$leaveRequest->employee_id}/leave-balance", [
                'leave_balance' => $newBalance
            ]);

            if (!$updateResp->successful()) {
                Log::error("Failed to update Employee Leave Balance: " . $updateResp->body());
            }
        } catch (\Exception $e) {
            Log::error("Failed to update Employee Leave Balance Exception: " . $e->getMessage());
        }

        return $updatedRequest;
    }

    /**
     * Finalize the Leave Request status to approved.
     */
    private function finalizeApproval($leaveRequestId)
    {
        $updatedRequest = $this->repository->updateStatus($leaveRequestId, 'approved');
        event(new LeaveRequestStatusUpdated($updatedRequest));
        return $updatedRequest;
    }

    /**
     * Enrich a collection of LeaveRequests with Employee & Approver names
     */
    public function enrichLeaveRequests($requests, $token)
    {
        $empCache = [];
        foreach ($requests as $req) {
            $empId = $req->employee_id;
            if (!array_key_exists($empId, $empCache)) {
                $data = $this->fetchEmployeeHierarchy($empId, $token);
                $empCache[$empId] = $data ? ($data['employee']['name'] ?? 'Unknown') : 'Unknown';
            }
            $req->setAttribute('employee_name', $empCache[$empId]);

            foreach ($req->approvals as $app) {
                $appId = $app->approver_id;
                if (!array_key_exists($appId, $empCache)) {
                    $data = $this->fetchEmployeeHierarchy($appId, $token);
                    $empCache[$appId] = $data ? ($data['employee']['name'] ?? 'Unknown') : 'Unknown';
                }
                $app->setAttribute('approver_name', $empCache[$appId]);
            }
        }
        return $requests;
    }

    /**
     * Enrich a collection of LeaveApprovals with Employee & Approver names
     */
    public function enrichApprovals($approvals, $token)
    {
        $empCache = [];
        foreach ($approvals as $app) {
            $appId = $app->approver_id;
            if (!array_key_exists($appId, $empCache)) {
                $data = $this->fetchEmployeeHierarchy($appId, $token);
                $empCache[$appId] = $data ? ($data['employee']['name'] ?? 'Unknown') : 'Unknown';
            }
            $app->setAttribute('approver_name', $empCache[$appId]);

            if ($app->leaveRequest) {
                $empId = $app->leaveRequest->employee_id;
                if (!array_key_exists($empId, $empCache)) {
                    $data = $this->fetchEmployeeHierarchy($empId, $token);
                    $empCache[$empId] = $data ? ($data['employee']['name'] ?? 'Unknown') : 'Unknown';
                }
                $app->leaveRequest->setAttribute('employee_name', $empCache[$empId]);
            }
        }
        return $approvals;
    }

    /**
     * Get Leave Requests from Subordinates
     */
    public function getSubordinateRequests($employeeId, $token = null)
    {
        $hierarchy = $this->fetchEmployeeHierarchy($employeeId, $token);

        $subordinateIds = [];
        if (!empty($hierarchy) && !empty($hierarchy['subordinates'])) {
            foreach ($hierarchy['subordinates'] as $sub) {
                $subordinateIds[] = $sub['id'];
            }
        }

        // If no subordinates, return empty collection
        if (empty($subordinateIds)) {
            return collect([]);
        }

        return $this->repository->getRequestsByEmployeeIds($subordinateIds);
    }

    /**
     * Fetch hierarchy from the Modul Employee service
     */
    private function fetchEmployeeHierarchy($employeeId, $token = null)
    {
        try {
            $request = Http::acceptJson();
            if ($token) {
                $request->withToken($token);
            }

            $response = $request->get("{$this->employeeApiUrl}/employees/{$employeeId}/hierarchy");

            if ($response->successful()) {
                return $response->json('data');
            } else {
                Log::error("Failed fetching hierarchy from Employee Module: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Failed fetching hierarchy from Employee Module exception: " . $e->getMessage());
        }

        return null;
    }
}
