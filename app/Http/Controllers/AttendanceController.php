<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    protected AttendanceService $attendanceService;
    protected ExportService $exportService;

    public function __construct(AttendanceService $attendanceService, ExportService $exportService)
    {
        $this->attendanceService = $attendanceService;
        $this->exportService = $exportService;
    }

    /**
     * Clock-in for the authenticated employee.
     *
     * POST /api/v1/attendance/clock-in
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clockIn()
    {
        $employeeId = auth('api')->id();
        $result = $this->attendanceService->clockIn($employeeId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Clock-out for the authenticated employee.
     *
     * POST /api/v1/attendance/clock-out
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clockOut()
    {
        $employeeId = auth('api')->id();
        $result = $this->attendanceService->clockOut($employeeId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get the authenticated employee's own attendance records.
     *
     * GET /api/v1/attendance/me
     * Query params: start_date, end_date, month, year
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function myAttendance(Request $request)
    {
        $employeeId = auth('api')->id();

        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // If month/year provided, use monthly report
        if ($request->filled(['month', 'year'])) {
            $report = $this->attendanceService->getMonthlyReport(
                $employeeId,
                $request->month,
                $request->year
            );
        } else {
            // Default to current month if no date range specified
            $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
            $endDate = $request->end_date ?? now()->toDateString();

            $report = $this->attendanceService->getAttendanceReport($employeeId, $startDate, $endDate);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get attendance report.
     * HR can view all employees, regular employees can only view their own.
     *
     * GET /api/v1/attendance/report
     * Query params: employee_id, start_date, end_date, month, year
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'nullable|integer|exists:employees,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $currentEmployee = auth('api')->user();
        $isHR = strtolower($currentEmployee->department) === 'hr'
            || strtolower($currentEmployee->position) === 'hr';

        // Determine date range
        if ($request->filled(['month', 'year'])) {
            $startDate = \Carbon\Carbon::create($request->year, $request->month, 1)->startOfMonth()->toDateString();
            $endDate = \Carbon\Carbon::create($request->year, $request->month, 1)->endOfMonth()->toDateString();
        } else {
            $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
            $endDate = $request->end_date ?? now()->toDateString();
        }

        // HR can view all or specific employee; non-HR can only view their own
        if ($isHR && !$request->filled('employee_id')) {
            // HR viewing all employees report
            $report = $this->attendanceService->getAllEmployeesReport($startDate, $endDate);
        } else {
            // Specific employee or own report
            $employeeId = $isHR
                ? ($request->employee_id ?? $currentEmployee->id)
                : $currentEmployee->id;

            $report = $this->attendanceService->getAttendanceReport($employeeId, $startDate, $endDate);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Export attendance report as CSV.
     *
     * GET /api/v1/attendance/report/export
     * Query params: employee_id, start_date, end_date, month, year
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'nullable|integer|exists:employees,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $currentEmployee = auth('api')->user();
        $isHR = strtolower($currentEmployee->department) === 'hr'
            || strtolower($currentEmployee->position) === 'hr';

        // Determine date range
        if ($request->filled(['month', 'year'])) {
            $startDate = \Carbon\Carbon::create($request->year, $request->month, 1)->startOfMonth()->toDateString();
            $endDate = \Carbon\Carbon::create($request->year, $request->month, 1)->endOfMonth()->toDateString();
        } else {
            $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
            $endDate = $request->end_date ?? now()->toDateString();
        }

        // HR can export all or specific employee; non-HR can only export own
        if ($isHR && !$request->filled('employee_id')) {
            $report = $this->attendanceService->getAllEmployeesReport($startDate, $endDate);
        } else {
            $employeeId = $isHR
                ? ($request->employee_id ?? $currentEmployee->id)
                : $currentEmployee->id;
            $report = $this->attendanceService->getAttendanceReport($employeeId, $startDate, $endDate);
        }

        $filename = "attendance_report_{$startDate}_to_{$endDate}.csv";

        return $this->exportService->exportAttendanceToCSV($report, $filename);
    }
}
