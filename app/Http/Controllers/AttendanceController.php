<?php

namespace App\Http\Controllers;

use App\Repositories\AttendanceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private AttendanceRepository $attendanceRepository;

    public function __construct(AttendanceRepository $attendanceRepository)
    {
        $this->attendanceRepository = $attendanceRepository;
    }

    /**
     * Check-in for today.
     * POST /api/v1/attendance/check-in
     */
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $employee = $request->user()->employee;

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil karyawan tidak ditemukan.',
                ], 404);
            }

            $attendance = $this->attendanceRepository->checkIn(
                $employee->id,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Check-in berhasil.',
                'data'    => $attendance,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check-out for today.
     * POST /api/v1/attendance/check-out
     */
    public function checkOut(Request $request): JsonResponse
    {
        try {
            $employee = $request->user()->employee;

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil karyawan tidak ditemukan.',
                ], 404);
            }

            $attendance = $this->attendanceRepository->checkOut($employee->id);

            return response()->json([
                'success' => true,
                'message' => 'Check-out berhasil.',
                'data'    => $attendance,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get today's attendance status.
     * GET /api/v1/attendance/today
     */
    public function today(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $attendance = $this->attendanceRepository->getTodayAttendance($employee->id);

        return response()->json([
            'success' => true,
            'data'    => $attendance,
        ]);
    }

    /**
     * Get attendance history.
     * GET /api/v1/attendance/history
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'status'     => 'nullable|in:present,late,absent,half_day',
            'per_page'   => 'nullable|integer|min:1|max:100',
        ]);

        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $history = $this->attendanceRepository->getHistory(
            $employee->id,
            $request->only(['start_date', 'end_date', 'status', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'data'    => $history,
        ]);
    }

    /**
     * Get monthly attendance summary.
     * GET /api/v1/attendance/summary/monthly
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year'  => 'nullable|integer|min:2020|max:2100',
        ]);

        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $summary = $this->attendanceRepository->getMonthlySummary(
            $employee->id,
            $month,
            $year
        );

        return response()->json([
            'success' => true,
            'data'    => $summary,
        ]);
    }

    /**
     * Get department attendance statistics.
     * GET /api/v1/attendance/stats/department
     * Only accessible by admin or manager.
     */
    public function departmentStats(Request $request): JsonResponse
    {
        $request->validate([
            'department' => 'required|string',
            'month'      => 'nullable|integer|min:1|max:12',
            'year'       => 'nullable|integer|min:2020|max:2100',
        ]);

        $user = $request->user();

        // Only admin and manager can view department stats
        if (!$user->isAdmin() && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat statistik departemen.',
            ], 403);
        }

        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $stats = $this->attendanceRepository->getDepartmentStats(
            $request->department,
            $month,
            $year
        );

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }
}
