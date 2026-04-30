<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AttendanceService;

class AttendanceController extends Controller
{
    private AttendanceService $service;

    public function __construct(AttendanceService $service)
    {
        $this->service = $service;
    }

    /**
     * POST /api/v1/attendance/check-in
     * Karyawan melakukan check-in kehadiran.
     */
    public function checkIn(Request $request)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()->id;
        $result = $this->service->checkIn($userId, $request->notes);

        $statusCode = $result['success'] ? 201 : 400;
        return response()->json($result, $statusCode);
    }

    /**
     * POST /api/v1/attendance/check-out
     * Karyawan melakukan check-out kehadiran.
     */
    public function checkOut(Request $request)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()->id;
        $result = $this->service->checkOut($userId, $request->notes);

        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    /**
     * GET /api/v1/attendance/history
     * Ambil riwayat kehadiran karyawan yang login.
     */
    public function history(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'per_page'   => 'nullable|integer|min:1|max:100',
        ]);

        $userId = $request->user()->id;
        $history = $this->service->getHistory(
            $userId,
            $request->start_date,
            $request->end_date,
            $request->per_page ?? 15
        );

        return response()->json([
            'success' => true,
            'data'    => $history,
        ]);
    }


}
