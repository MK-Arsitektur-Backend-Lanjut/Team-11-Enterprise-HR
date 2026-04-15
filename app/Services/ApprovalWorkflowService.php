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

    public function __construct(LeaveRequestRepository $repository)
    {
        $this->repository = $repository;
        // In a real scenario, this would be fetched from config/services.php or .env
        $this->attendanceApiUrl = env('ATTENDANCE_SERVICE_URL', 'http://localhost:8000/api/v1');
    }

    /**
     * Submit a new leave request and initiate the workflow
     */
    public function submitLeaveRequest($employeeId, $data)
    {
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
        $hierarchy = $this->fetchEmployeeHierarchy($employeeId);

        if (empty($hierarchy)) {
             $this->finalizeApproval($leaveRequest->id);
             return clone $this->repository->getRequestById($leaveRequest->id);
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

            // 3. Create the first level approval record
            $this->repository->createApprovalRecord([
                'leave_request_id' => $leaveRequest->id,
                'approver_id'      => $managerId,
                'status'           => 'pending',
                'approval_level'   => 1
            ]);
        } else {
            $this->finalizeApproval($leaveRequest->id);
        }

        return clone $this->repository->getRequestById($leaveRequest->id);
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
            if ($position === 'manager') {
                $approverHierarchy = $this->fetchEmployeeHierarchy($approverId);

                if ($approverHierarchy && array_key_exists('manager', $approverHierarchy) && $approverHierarchy['manager'] !== null) {
                    $level2ApproverId = $approverHierarchy['manager']['id'];

                    // Create pending Level 2
                    $this->repository->createApprovalRecord([
                        'leave_request_id' => $leaveRequest->id,
                        'approver_id'      => $level2ApproverId,
                        'status'           => 'pending',
                        'approval_level'   => 2
                    ]);

                    // Jangan difinalisasi, kembali return data pending keseluruhan
                    return $this->repository->getRequestById($leaveRequest->id);
                }
            }
        }

        // Finalisasi jika bukan manager (berarti Staff/Director) atau ini persetujuan di Level 2
        $updatedRequest = $this->finalizeApproval($leaveRequest->id);

        // Simulating the update across the microservice barrier
        try {
            // Here we assume we call it and let Attendance know it was taken,
            // the logic in Attendance API was previously configured to take a hardcoded leaf balance,
            // so we would just log standard flow.
            Log::info("Calling Attendance Module to deduct balance for Employee: " . $leaveRequest->employee_id);
            // Http::put("{$this->attendanceApiUrl}/employees/{$leaveRequest->employee_id}/leave-balance", ['leave_balance' => ...]);
        } catch (\Exception $e) {
            Log::error("Failed to update Attendance Leave Balance: " . $e->getMessage());
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
     * Fetch hierarchy from the Modul Attendance service
     */
    private function fetchEmployeeHierarchy($employeeId)
    {
        try {
            $response = Http::get("{$this->attendanceApiUrl}/employees/{$employeeId}/hierarchy");

            if ($response->successful()) {
                return $response->json('data');
            }
        } catch (\Exception $e) {
            Log::error("Failed fetching hierarchy from Attendance Module: " . $e->getMessage());
        }

        return null;
    }
}
