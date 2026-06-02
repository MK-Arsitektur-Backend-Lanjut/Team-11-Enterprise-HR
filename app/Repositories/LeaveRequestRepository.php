<?php

namespace App\Repositories;

use App\Models\LeaveRequest;
use App\Models\LeaveApproval;

class LeaveRequestRepository
{
    public function createRequest($data)
    {
        return LeaveRequest::create($data);
    }

    public function getRequestsByEmployee($employeeId)
    {
        return LeaveRequest::where('employee_id', $employeeId)->with('approvals')->orderByDesc('created_at')->get();
    }

    public function getRequestsByEmployeeIds(array $employeeIds)
    {
        return LeaveRequest::whereIn('employee_id', $employeeIds)->with('approvals')->orderByDesc('created_at')->get();
    }

    public function getAllRequests()
    {
        return LeaveRequest::with('approvals')->orderByDesc('created_at')->get();
    }

    public function updateStatus($id, $status)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $leaveRequest->status = $status;
        $leaveRequest->save();

        return $leaveRequest;
    }

    public function getRequestById($id)
    {
        return LeaveRequest::with('approvals')->findOrFail($id);
    }

    public function createApprovalRecord($data)
    {
        return LeaveApproval::create($data);
    }

    public function getPendingApprovalsFor($approverId)
    {
        return LeaveApproval::where('approver_id', $approverId)
            ->where('status', 'pending')
            ->with('leaveRequest')
            ->get();
    }

    public function getApprovalRecordById($id)
    {
        return LeaveApproval::findOrFail($id);
    }

    public function updateApprovalRecord($approvalId, $status, $notes)
    {
        $approval = LeaveApproval::findOrFail($approvalId);
        $approval->status = $status;
        $approval->notes = $notes;
        $approval->save();

        return $approval;
    }
}
