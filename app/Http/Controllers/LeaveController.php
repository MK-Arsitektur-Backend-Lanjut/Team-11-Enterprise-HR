<?php

namespace App\Http\Controllers;

use App\Services\LeaveService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    protected LeaveService $leaveService;
    protected ExportService $exportService;

    public function __construct(LeaveService $leaveService, ExportService $exportService)
    {
        $this->leaveService = $leaveService;
        $this->exportService = $exportService;
    }

    /**
     * List all leave records.
     * HR can view all; regular employees can only view their own.
     *
     * GET /api/v1/leaves
     * Query params: employee_id, status, leave_type, start_date, end_date
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $currentEmployee = auth('api')->user();
        $isHR = strtolower($currentEmployee->department) === 'hr'
            || strtolower($currentEmployee->position) === 'hr';

        $filters = $request->only(['employee_id', 'status', 'leave_type', 'start_date', 'end_date']);

        // Non-HR can only see their own leaves
        if (!$isHR) {
            $filters['employee_id'] = $currentEmployee->id;
        }

        $leaves = $this->leaveService->getAllLeaves($filters);

        return response()->json([
            'success' => true,
            'data' => $leaves,
        ]);
    }

    /**
     * Get a specific leave record.
     *
     * GET /api/v1/leaves/{id}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $currentEmployee = auth('api')->user();
        $isHR = strtolower($currentEmployee->department) === 'hr'
            || strtolower($currentEmployee->position) === 'hr';

        $leave = $this->leaveService->getLeaveById($id);

        if (!$leave) {
            return response()->json([
                'success' => false,
                'message' => 'Data cuti tidak ditemukan.',
            ], 404);
        }

        // Non-HR can only view their own leave
        if (!$isHR && $leave->employee_id !== $currentEmployee->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat data cuti ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $leave,
        ]);
    }

    /**
     * Get the authenticated employee's own leave records.
     *
     * GET /api/v1/leaves/me
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function myLeaves()
    {
        $employeeId = auth('api')->id();
        $leaves = $this->leaveService->getEmployeeLeaves($employeeId);

        return response()->json([
            'success' => true,
            'data' => $leaves,
        ]);
    }

    /**
     * Sync leave data from Approval Workflow Service.
     *
     * POST /api/v1/leaves/sync
     * Query params: employee_id (optional, sync for specific employee)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncFromApproval(Request $request)
    {
        $currentEmployee = auth('api')->user();
        $isHR = strtolower($currentEmployee->department) === 'hr'
            || strtolower($currentEmployee->position) === 'hr';

        // Only HR can trigger full sync; regular employees sync their own
        $employeeId = $isHR
            ? $request->employee_id
            : $currentEmployee->id;

        $result = $this->leaveService->syncFromApprovalService($employeeId);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Export leave data for payroll as CSV.
     *
     * GET /api/v1/leaves/export/payroll
     * Query params: start_date (required), end_date (required)
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function exportForPayroll(Request $request)
    {
        $currentEmployee = auth('api')->user();
        $isHR = strtolower($currentEmployee->department) === 'hr'
            || strtolower($currentEmployee->position) === 'hr';

        if (!$isHR) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya HR yang dapat mengekspor data cuti untuk penggajian.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $payrollData = $this->leaveService->getPayrollLeaveData(
            $request->start_date,
            $request->end_date
        );

        $filename = "leave_payroll_{$request->start_date}_to_{$request->end_date}.csv";

        return $this->exportService->exportLeavePayrollToCSV($payrollData, $filename);
    }
}
