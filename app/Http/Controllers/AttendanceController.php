<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Attendance", description: "API Endpoints for Attendance and Reporting")]
class AttendanceController extends Controller
{
    protected AttendanceService $attendanceService;
    protected ExportService $exportService;

    public function __construct(AttendanceService $attendanceService, ExportService $exportService)
    {
        $this->attendanceService = $attendanceService;
        $this->exportService = $exportService;
    }

    #[OA\Post(
        path: "/api/v1/attendance/clock-in",
        summary: "Clock-in for the authenticated employee",
        security: [["bearerAuth" => []]],
        tags: ["Attendance"]
    )]
    #[OA\Response(
        response: 200,
        description: "Successfully clocked in",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "data", ref: "#/components/schemas/Attendance")
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Already clocked in or bad request")]
    public function clockIn()
    {
        $employeeId = auth('api')->id();
        $result = $this->attendanceService->clockIn($employeeId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    #[OA\Post(
        path: "/api/v1/attendance/clock-out",
        summary: "Clock-out for the authenticated employee",
        security: [["bearerAuth" => []]],
        tags: ["Attendance"]
    )]
    #[OA\Response(
        response: 200,
        description: "Successfully clocked out",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "data", ref: "#/components/schemas/Attendance")
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Not clocked in or already clocked out")]
    public function clockOut()
    {
        $employeeId = auth('api')->id();
        $result = $this->attendanceService->clockOut($employeeId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    #[OA\Get(
        path: "/api/v1/attendance/me",
        summary: "Get the authenticated employee's own attendance records",
        security: [["bearerAuth" => []]],
        tags: ["Attendance"]
    )]
    #[OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "month", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "year", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "data", type: "object")
            ]
        )
    )]
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

        if ($request->filled(['month', 'year'])) {
            $report = $this->attendanceService->getMonthlyReport(
                $employeeId,
                $request->month,
                $request->year
            );
        } else {
            $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
            $endDate = $request->end_date ?? now()->toDateString();

            $report = $this->attendanceService->getAttendanceReport($employeeId, $startDate, $endDate);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    #[OA\Get(
        path: "/api/v1/attendance/report",
        summary: "Get attendance report (HR can view all, regular can only view their own)",
        security: [["bearerAuth" => []]],
        tags: ["Attendance"]
    )]
    #[OA\Parameter(name: "employee_id", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "month", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "year", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "data", type: "object")
            ]
        )
    )]
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

        if ($request->filled(['month', 'year'])) {
            $startDate = \Carbon\Carbon::create($request->year, $request->month, 1)->startOfMonth()->toDateString();
            $endDate = \Carbon\Carbon::create($request->year, $request->month, 1)->endOfMonth()->toDateString();
        } else {
            $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
            $endDate = $request->end_date ?? now()->toDateString();
        }

        if ($isHR && !$request->filled('employee_id')) {
            $report = $this->attendanceService->getAllEmployeesReport($startDate, $endDate);
        } else {
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

    #[OA\Get(
        path: "/api/v1/attendance/report/export",
        summary: "Export attendance report as CSV",
        security: [["bearerAuth" => []]],
        tags: ["Attendance"]
    )]
    #[OA\Parameter(name: "employee_id", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "month", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "year", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "CSV File Download",
        content: new OA\MediaType(
            mediaType: "text/csv",
            schema: new OA\Schema(type: "string", format: "binary")
        )
    )]
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

        if ($request->filled(['month', 'year'])) {
            $startDate = \Carbon\Carbon::create($request->year, $request->month, 1)->startOfMonth()->toDateString();
            $endDate = \Carbon\Carbon::create($request->year, $request->month, 1)->endOfMonth()->toDateString();
        } else {
            $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
            $endDate = $request->end_date ?? now()->toDateString();
        }

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
