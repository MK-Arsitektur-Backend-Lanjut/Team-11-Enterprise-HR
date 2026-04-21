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
     * POST /api/v1/leaves
     * Submit a leave request.
     */
    public function store(Request $request)
    {
        $employeeId = $request->attributes->get('employee_id');

        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string',
            'type'       => 'required|string'
        ]);

        try {
            $token = $request->bearerToken();
            $leaveRequest = $this->workflowService->submitLeaveRequest($employeeId, $request->all(), $token);

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
     * GET /api/v1/leaves/my-requests
     * View leave history and status by authenticated employee ID.
     */
    public function myRequests(Request $request)
    {
        $employeeId = $request->attributes->get('employee_id');
        $requests = $this->repository->getRequestsByEmployee($employeeId);
        $enrichedRequests = $this->workflowService->enrichLeaveRequests($requests, $request->bearerToken());

        $leavesBalances = $this->workflowService->getEmployeeLeaveBalance($employeeId, $request->bearerToken());

        return response()->json([
            'success'         => true,
            'leaves_balances' => $leavesBalances,
            'data'            => $enrichedRequests
        ], 200);
    }

    /**
     * GET /api/v1/leaves/all
     * View all leaves from subordinates only.
     */
    public function allRequests(Request $request)
    {
        $employeeId = $request->attributes->get('employee_id');
        $token = $request->bearerToken();

        $requests = $this->workflowService->getSubordinateRequests($employeeId, $token);
        $enrichedRequests = $this->workflowService->enrichLeaveRequests($requests, $token);

        return response()->json([
            'success' => true,
            'data'    => $enrichedRequests
        ], 200);
    }
}
