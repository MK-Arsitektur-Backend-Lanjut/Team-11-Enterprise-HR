<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApprovalWorkflowService;
use App\Repositories\LeaveRequestRepository;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Leave Request", description: "API Endpoints for Leave Requests")]
class LeaveController extends Controller
{
    private $workflowService;
    private $repository;

    public function __construct(ApprovalWorkflowService $workflowService, LeaveRequestRepository $repository)
    {
        $this->workflowService = $workflowService;
        $this->repository = $repository;
    }

    #[OA\Post(
        path: "/api/leaves",
        summary: "Submit a leave request",
        security: [["bearerAuth" => []]],
        tags: ["Leave Request"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["start_date", "end_date", "reason", "type"],
            properties: [
                new OA\Property(property: "start_date", type: "string", format: "date", example: "2024-10-01"),
                new OA\Property(property: "end_date", type: "string", format: "date", example: "2024-10-03"),
                new OA\Property(property: "reason", type: "string", example: "Family vacation"),
                new OA\Property(property: "type", type: "string", example: "annual")
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Leave request submitted successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "data", ref: "#/components/schemas/LeaveRequest")
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Validation error or insufficient leave balance")]
    public function store(Request $request)
    {
        $employee = auth('api')->user();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string',
            'type'       => 'required|string'
        ]);

        try {
            $leaveRequest = $this->workflowService->submitLeaveRequest($employee->id, $request->all());

            if (is_array($leaveRequest) && isset($leaveRequest['status']) && $leaveRequest['status'] === 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => $leaveRequest['message'],
                    'leaves_balances' => $leaveRequest['leaves_balances']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave request submitted successfully.',
                'data'    => $leaveRequest
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed submitting leave request: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/leaves/my-requests",
        summary: "View leave history and status by authenticated employee",
        security: [["bearerAuth" => []]],
        tags: ["Leave Request"]
    )]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "leaves_balances", type: "integer"),
                new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/LeaveRequest"))
            ]
        )
    )]
    public function myRequests(Request $request)
    {
        $employee = auth('api')->user();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $requests = $this->repository->getRequestsByEmployee($employee->id);

        $leavesBalances = $this->workflowService->getEmployeeLeaveBalance($employee->id);

        return response()->json([
            'success'         => true,
            'leaves_balances' => $leavesBalances,
            'data'            => $requests
        ], 200);
    }

    #[OA\Get(
        path: "/api/leaves/subordinates",
        summary: "View all leaves from subordinates only",
        security: [["bearerAuth" => []]],
        tags: ["Leave Request"]
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
    public function subordinateRequests(Request $request)
    {
        $employee = auth('api')->user();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $requests = $this->workflowService->getSubordinateRequests($employee->id);

        return response()->json([
            'success' => true,
            'data'    => $requests
        ], 200);
    }
}
