<?php

namespace App\Services;

use App\Models\Employee;
use App\Repositories\LeaveRequestRepository;
use App\Events\LeaveRequestStatusUpdated;
use App\Services\EmployeeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApprovalWorkflowService
{
    private $repository;
    private $employeeService;

    public function __construct(LeaveRequestRepository $repository, EmployeeService $employeeService)
    {
        $this->repository = $repository;
        $this->employeeService = $employeeService;
    }

    /**
     * Fetch leave balance from Employee Model (Monolith)
     */
    public function getEmployeeLeaveBalance($employeeId)
    {
        try {
            // Redis Cache: data leave_balance jarang berubah, sering dibaca
            return Cache::remember(
                "employee:{$employeeId}:leave_balance",
                now()->addMinutes(30),
                function () use ($employeeId) {
                    $employee = Employee::select('id', 'leave_balance')->find($employeeId);
                    return $employee ? ($employee->leave_balance ?? 0) : 0;
                }
            );
        } catch (\Exception $e) {
            Log::error("Failed fetching employee balance: " . $e->getMessage());
            // Fallback: query langsung ke database tanpa cache
            $employee = Employee::select('id', 'leave_balance')->find($employeeId);
            return $employee ? ($employee->leave_balance ?? 0) : 0;
        }
    }

    /**
     * Submit a new leave request and initiate the workflow
     */
    public function submitLeaveRequest($employeeId, $data)
    {
        // 0. Cek Saldo Cuti dari Employee Model (Eager Load + Select Optimization)
        $employee = Employee::select('id', 'leave_balance', 'position', 'manager_id')
            ->with(['manager:id,manager_id', 'manager.manager:id'])
            ->find($employeeId);
        
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
                $employee = Employee::select('id', 'position')->find($leaveRequest->employee_id);
                $position = strtolower($employee->position ?? '');

                if ($position === 'manager') {
                    // Cari approver (manager level 1) — eager load manager untuk level 2
                    $approver = Employee::select('id', 'manager_id')->with('manager:id')->find($approverId);
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

            $updatedRequest->load(['employee', 'approvals.approver']);
            event(new LeaveRequestStatusUpdated($updatedRequest));
            return $updatedRequest;
        }

        // Jika disetujui, dan kita berada di Level 1
        if ($expectedLevel === 1) {
            $employee = Employee::select('id', 'position')->find($leaveRequest->employee_id);
            $position = strtolower($employee->position ?? '');

            // Sesuai rules: Jika posisi "Manager", maka masih butuh Level 2
            $hasPendingLevel2 = \App\Models\LeaveApproval::where('leave_request_id', $leaveRequest->id)
                                ->where('approval_level', 2)
                                ->where('status', 'pending')
                                ->exists();

            if ($hasPendingLevel2) {
                $result = $this->repository->getRequestById($leaveRequest->id);
                return $result;
            }
        }

        // Finalisasi jika bukan manager atau ini persetujuan di Level 2
        $updatedRequest = $this->finalizeApproval($leaveRequest->id);

        return $updatedRequest;
    }

    /**
     * Finalize the Leave Request status to approved and deduct leave balance automatically.
     */
    private function finalizeApproval($leaveRequestId)
    {
        $updatedRequest = $this->repository->updateStatus($leaveRequestId, 'approved');

        // Potong saldo cuti via EmployeeService secara otomatis
        try {
            $start = new \DateTime($updatedRequest->start_date);
            $end = new \DateTime($updatedRequest->end_date);
            $daysRequested = $start->diff($end)->days + 1;

            $employee = $this->employeeService->getEmployeeById($updatedRequest->employee_id);
            if ($employee) {
                $currentBalance = $employee->leave_balance ?? 0;
                $newBalance = max(0, $currentBalance - $daysRequested);

                Log::info("Deducting leave balance for Employee ID: {$updatedRequest->employee_id}. Old: {$currentBalance}, New: {$newBalance}");

                $this->employeeService->updateLeaveBalance($updatedRequest->employee_id, $newBalance);

                // Invalidasi Redis cache setelah saldo cuti berubah
                Cache::forget("employee:{$updatedRequest->employee_id}:leave_balance");
            }
        } catch (\Exception $e) {
            Log::error("Failed to automatically update Employee Leave Balance: " . $e->getMessage());
        }

        $updatedRequest->load(['employee', 'approvals.approver']);
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
