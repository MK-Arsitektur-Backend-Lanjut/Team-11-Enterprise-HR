<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApprovalWorkflowService;
use App\Repositories\LeaveRequestRepository;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Approval", description: "API Endpoints for Approval")]
class ApprovalController extends Controller
{
    private $workflowService;
    private $repository;

    public function __construct(ApprovalWorkflowService $workflowService, LeaveRequestRepository $repository)
    {
        $this->workflowService = $workflowService;
        $this->repository = $repository;
    }

    #[OA\Get(
        path: "/api/approvals/pending",
        summary: "Get pending approvals for the current approver/manager",
        security: [["bearerAuth" => []]],
        tags: ["Approval"]
    )]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/LeaveRequest"))
            ]
        )
    )]
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

    #[OA\Post(
        path: "/api/approvals/level-1/{leave_request_id}",
        summary: "Approve or reject a leave request with notes",
        security: [["bearerAuth" => []]],
        tags: ["Approval"]
    )]
    #[OA\Parameter(name: "leave_request_id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["status"],
            properties: [
                new OA\Property(property: "status", type: "string", enum: ["approved", "rejected"]),
                new OA\Property(property: "notes", type: "string", nullable: true)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Level 1 leave request processed successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "request", ref: "#/components/schemas/LeaveRequest")
                ])
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Validation error")]
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

    #[OA\Post(
        path: "/api/approvals/level-2/{leave_request_id}",
        summary: "Approve or reject a leave request with notes at Manager level 2",
        security: [["bearerAuth" => []]],
        tags: ["Approval"]
    )]
    #[OA\Parameter(name: "leave_request_id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["status"],
            properties: [
                new OA\Property(property: "status", type: "string", enum: ["approved", "rejected"]),
                new OA\Property(property: "notes", type: "string", nullable: true)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Level 2 leave request processed successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "request", ref: "#/components/schemas/LeaveRequest")
                ])
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Validation error")]
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
