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
     * GET /api/approvals/pending
     * Get pending approvals for the current approver/manager.
     */
    public function pending(Request $request)
    {
        $employee = auth('api')->user();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $pendingApprovals = $this->repository->getPendingApprovalsFor($employee->id);

        return response()->json([
            'success' => true,
            'data'    => $pendingApprovals
        ], 200);
    }

    /**
     * POST /api/approvals/level-1/{leave_request_id}
     * Approve or reject a leave request with notes.
     */
    public function approveLevel1(Request $request, $leaveRequestId)
    {
        $employee = auth('api')->user();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        return $this->handleApproval($request, $leaveRequestId, $employee->id, 1);
    }

    /**
     * POST /api/approvals/level-2/{leave_request_id}
     * Approve or reject a leave request with notes at Manager level 2.
     */
    public function approveLevel2(Request $request, $leaveRequestId)
    {
        $employee = auth('api')->user();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        return $this->handleApproval($request, $leaveRequestId, $employee->id, 2);
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
