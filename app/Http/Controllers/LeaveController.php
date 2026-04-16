<?php

namespace App\Http\Controllers;

use App\Repositories\LeaveRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    private LeaveRepository $leaveRepository;

    public function __construct(LeaveRepository $leaveRepository)
    {
        $this->leaveRepository = $leaveRepository;
    }

    /**
     * Apply for leave.
     * POST /api/v1/leaves
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'type'       => 'required|in:annual,sick,personal,unpaid',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string|max:1000',
        ]);

        try {
            $employee = $request->user()->employee;

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil karyawan tidak ditemukan.',
                ], 404);
            }

            $leave = $this->leaveRepository->applyLeave(
                $employee->id,
                $request->only(['type', 'start_date', 'end_date', 'reason'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil.',
                'data'    => $leave,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Approve a leave request (manager/admin only).
     * PUT /api/v1/leaves/{id}/approve
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui cuti.',
            ], 403);
        }

        try {
            $leave = $this->leaveRepository->approveLeave($id, $user->employee->id);

            return response()->json([
                'success' => true,
                'message' => 'Cuti berhasil disetujui.',
                'data'    => $leave,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject a leave request (manager/admin only).
     * PUT /api/v1/leaves/{id}/reject
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menolak cuti.',
            ], 403);
        }

        try {
            $leave = $this->leaveRepository->rejectLeave($id, $user->employee->id);

            return response()->json([
                'success' => true,
                'message' => 'Cuti berhasil ditolak.',
                'data'    => $leave,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get leave history.
     * GET /api/v1/leaves
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'type'       => 'nullable|in:annual,sick,personal,unpaid',
            'status'     => 'nullable|in:pending,approved,rejected',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'per_page'   => 'nullable|integer|min:1|max:100',
        ]);

        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $history = $this->leaveRepository->getLeaveHistory(
            $employee->id,
            $request->only(['type', 'status', 'start_date', 'end_date', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'data'    => $history,
        ]);
    }

    /**
     * Get leave balance.
     * GET /api/v1/leaves/balance
     */
    public function balance(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $balance = $this->leaveRepository->getLeaveBalance($employee->id);

        return response()->json([
            'success' => true,
            'data'    => $balance,
        ]);
    }
}
