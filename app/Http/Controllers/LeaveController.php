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
     * POST /api/v1/leaves/{employee_id}
     * Submit a leave request.
     */
    public function store(Request $request, $employeeId)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string',
            'type'       => 'required|string'
        ]);

        try {
            $leaveRequest = $this->workflowService->submitLeaveRequest($employeeId, $request->all());

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
     * GET /api/v1/leaves/my-requests/{employee_id}
     * View leave history and status by employee ID.
     */
    public function myRequests($employeeId)
    {
        $requests = $this->repository->getRequestsByEmployee($employeeId);

        return response()->json([
            'success' => true,
            'data'    => $requests
        ], 200);
    }

    /**
     * GET /api/v1/leaves/all
     * View all leaves across the company.
     */
    public function allRequests()
    {
        $requests = $this->repository->getAllRequests();

        return response()->json([
            'success' => true,
            'data'    => $requests
        ], 200);
    }
}
