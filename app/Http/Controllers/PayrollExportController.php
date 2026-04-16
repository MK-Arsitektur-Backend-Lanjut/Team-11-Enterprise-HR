<?php

namespace App\Http\Controllers;

use App\Repositories\PayrollExportRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollExportController extends Controller
{
    private PayrollExportRepository $payrollExportRepository;

    public function __construct(PayrollExportRepository $payrollExportRepository)
    {
        $this->payrollExportRepository = $payrollExportRepository;
    }

    /**
     * Export leave data as CSV for payroll.
     * GET /api/v1/payroll/export/leave
     * Admin only.
     */
    public function exportLeaveCsv(Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang dapat mengekspor data penggajian.',
            ], 403);
        }

        $request->validate([
            'month'      => 'nullable|integer|min:1|max:12',
            'year'       => 'nullable|integer|min:2020|max:2100',
            'department' => 'nullable|string',
            'type'       => 'nullable|in:annual,sick,personal,unpaid',
            'status'     => 'nullable|in:pending,approved,rejected',
        ]);

        $filters = $request->only(['month', 'year', 'department', 'type', 'status']);
        $exportData = $this->payrollExportRepository->exportLeaveData($filters);
        $csvContent = $this->payrollExportRepository->generateCsvContent($exportData);

        $month = $filters['month'] ?? now()->month;
        $year = $filters['year'] ?? now()->year;
        $filename = "leave_export_{$year}_{$month}.csv";

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Get payroll summary (leave aggregation).
     * GET /api/v1/payroll/summary
     * Admin only.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang dapat melihat ringkasan penggajian.',
            ], 403);
        }

        $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year'  => 'nullable|integer|min:2020|max:2100',
        ]);

        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $summary = $this->payrollExportRepository->getPayrollSummary($month, $year);

        return response()->json([
            'success' => true,
            'data'    => $summary,
        ]);
    }
}
