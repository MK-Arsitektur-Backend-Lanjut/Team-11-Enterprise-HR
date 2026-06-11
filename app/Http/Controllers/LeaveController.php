<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApprovalWorkflowService;
use App\Repositories\LeaveRequestRepository;

class LeaveController extends Controller
{
    private $workflowService;
    private $repository;

    public function __construct(ApprovalWorkflowService $workflowService, LeaveRequestRepository $repository)
    {
        $this->workflowService = $workflowService;
        $this->repository = $repository;
    }

    /**
     * POST /api/leaves
     * Submit a leave request.
     */
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

    /**
     * GET /api/leaves/my-requests
     * View leave history and status by authenticated employee ID.
     */
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

    /**
     * GET /api/leaves/subordinates
     * View all leaves from subordinates only.
     */
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
