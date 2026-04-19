<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApprovalWorkflowService;
use App\Repositories\LeaveRequestRepository;

class ApprovalController extends Controller
{
    private $workflowService;
    private $repository;

    public function __construct(ApprovalWorkflowService $workflowService, LeaveRequestRepository $repository)
    {
        $this->workflowService = $workflowService;
        $this->repository = $repository;
    }

    /**
     * GET /api/v1/approvals/pending
     * Get pending approvals for the current approver/manager.
     */
    public function pending(Request $request)
    {
        $approverId = $request->attributes->get('employee_id');
        $pendingApprovals = $this->repository->getPendingApprovalsFor($approverId);

        return response()->json([
            'success' => true,
            'data'    => $pendingApprovals
        ], 200);
    }

    /**
     * POST /api/v1/approvals/level-1/{leave_request_id}
     * Approve or reject a leave request with notes.
     */
    public function approveLevel1(Request $request, $leaveRequestId)
    {
        $approverId = $request->attributes->get('employee_id');
        return $this->handleApproval($request, $leaveRequestId, $approverId, 1);
    }

    /**
     * POST /api/v1/approvals/level-2/{leave_request_id}
     * Approve or reject a leave request with notes at Manager level 2.
     */
    public function approveLevel2(Request $request, $leaveRequestId)
    {
        $approverId = $request->attributes->get('employee_id');
        return $this->handleApproval($request, $leaveRequestId, $approverId, 2);
    }

    /**
     * Internal Handler to process approval level.
     */
    private function handleApproval(Request $request, $leaveRequestId, $approverId, $expectedLevel)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes'  => 'nullable|string'
        ]);

        try {
            $leaveRequest = $this->repository->getRequestById($leaveRequestId);

            $status = $request->input('status');
            $notes = $request->input('notes');

            // Will pass into service without explicit approvalRecordId
            $processedRequest = $this->workflowService->processApproval(
                $leaveRequest,
                $approverId,
                $status,
                $notes,
                $expectedLevel
            );

            return response()->json([
                'success' => true,
                'message' => "Level {$expectedLevel} leave request processed successfully.",
                'data'    => [
                    'request' => $processedRequest
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed processing approval: ' . $e->getMessage()
            ], 500);
        }
    }
}
