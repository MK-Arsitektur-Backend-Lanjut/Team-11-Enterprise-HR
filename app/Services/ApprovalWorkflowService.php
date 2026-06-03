<?php

namespace App\Services;

use App\Models\Employee;
use App\Repositories\LeaveRequestRepository;
use App\Events\LeaveRequestStatusUpdated;
use Illuminate\Support\Facades\Log;

class ApprovalWorkflowService
{
    private $repository;

    public function __construct(LeaveRequestRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Fetch leave balance from Employee Model (Monolith)
     */
    public function getEmployeeLeaveBalance($employeeId)
    {
        try {
            $employee = Employee::find($employeeId);
            if ($employee) {
                return $employee->leave_balance ?? 0;
            }
        } catch (\Exception $e) {
            Log::error("Failed fetching employee balance: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Submit a new leave request and initiate the workflow
     */
    public function submitLeaveRequest($employeeId, $data)
    {
        // 0. Cek Saldo Cuti dari Employee Model
        $employee = Employee::find($employeeId);
        
        if (!$employee) {
            throw new \Exception("Employee not found.");
        }

        $leaveBalances = $employee->leave_balance ?? 0;

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

        // 2. Get employee hierarchy directly from database
        $employeeData = $employee->toArray();
        $manager = $employee->manager;

        // Jika Position CEO, maka Auto-Approved
        if (strtolower($employee->position) === 'ceo') {
             $this->finalizeApproval($leaveRequest->id);
             $result = $this->repository->getRequestById($leaveRequest->id);
             $result->load(['employee', 'approvals.approver']);
             $result->setAttribute('leaves_balances', $leaveBalances);
             return $result;
        }

        // Jika manager tidak null
        if ($manager) {
            $managerId = $manager->id;

            // 3. Create the first level approval record
            $this->repository->createApprovalRecord([
                'leave_request_id' => $leaveRequest->id,
                'approver_id'      => $managerId,
                'status'           => 'pending',
                'approval_level'   => 1
            ]);

            // Add second level if manager also has a manager
            $level2Manager = $manager->manager;
            if ($level2Manager) {
                $this->repository->createApprovalRecord([
                    'leave_request_id' => $leaveRequest->id,
                    'approver_id'      => $level2Manager->id,
                    'status'           => 'pending',
                    'approval_level'   => 2
                ]);
            }
        } else {
            // No manager = auto approve
            $this->finalizeApproval($leaveRequest->id);
        }

        $result = $this->repository->getRequestById($leaveRequest->id);
        $result->load(['employee', 'approvals.approver']);
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
                $employee = Employee::find($leaveRequest->employee_id);
                $position = strtolower($employee->position ?? '');

                if ($position === 'manager') {
                    // Cari approver (manager level 1)
                    $approver = Employee::find($approverId);
                    if ($approver && $approver->manager) {
                        $level2ApproverId = $approver->manager->id;
                        
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
            $updatedRequest->load(['employee', 'approvals.approver']);
            return $updatedRequest;
        }

        // Jika disetujui, dan kita berada di Level 1
        if ($expectedLevel === 1) {
            $employee = Employee::find($leaveRequest->employee_id);
            $position = strtolower($employee->position ?? '');

            // Sesuai rules: Jika posisi "Manager", maka masih butuh Level 2
            $hasPendingLevel2 = \App\Models\LeaveApproval::where('leave_request_id', $leaveRequest->id)
                                ->where('approval_level', 2)
                                ->where('status', 'pending')
                                ->exists();

            if ($hasPendingLevel2) {
                $result = $this->repository->getRequestById($leaveRequest->id);
                $result->load(['employee', 'approvals.approver']);
                return $result;
            }
        }

        // Finalisasi jika bukan manager atau ini persetujuan di Level 2
        $updatedRequest = $this->finalizeApproval($leaveRequest->id);

        // Potong saldo cuti dari Employee Model
        $start = new \DateTime($leaveRequest->start_date);
        $end = new \DateTime($leaveRequest->end_date);
        $daysRequested = $start->diff($end)->days + 1;

        try {
            $employee = Employee::find($leaveRequest->employee_id);
            if ($employee) {
                $currentBalance = $employee->leave_balance ?? 0;
                $newBalance = max(0, $currentBalance - $daysRequested);

                Log::info("Deducting leave balance for Employee ID: {$leaveRequest->employee_id}. Old: {$currentBalance}, New: {$newBalance}");

                $employee->leave_balance = $newBalance;
                $employee->save();
            }
        } catch (\Exception $e) {
            Log::error("Failed to update Employee Leave Balance: " . $e->getMessage());
        }

        $updatedRequest->load(['employee', 'approvals.approver']);
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
     * Enrich a collection of LeaveRequests with Employee & Approver names (using eager loading)
     */
    public function enrichLeaveRequests($requests)
    {
        // Already loaded via eager loading in controller
        return $requests;
    }

    /**
     * Enrich a collection of LeaveApprovals with Employee & Approver names (using eager loading)
     */
    public function enrichApprovals($approvals)
    {
        // Already loaded via eager loading in controller
        return $approvals;
    }

    /**
     * Get Leave Requests from Subordinates
     */
    public function getSubordinateRequests($employeeId)
    {
        $employee = Employee::with('subordinates')->find($employeeId);

        if (!$employee || $employee->subordinates->isEmpty()) {
            return collect([]);
        }

        $subordinateIds = $employee->subordinates->pluck('id')->toArray();

        return $this->repository->getRequestsByEmployeeIds($subordinateIds);
    }
}
