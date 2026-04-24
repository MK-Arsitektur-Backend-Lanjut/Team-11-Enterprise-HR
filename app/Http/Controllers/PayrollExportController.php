<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AttendanceService;

class PayrollExportController extends Controller
{
    private AttendanceService $service;

    public function __construct(AttendanceService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/v1/payroll/export
     * Ekspor data kehadiran dan cuti untuk penggajian dalam format JSON.
     * Bisa dikonversi ke CSV oleh client atau frontend.
     */
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $token = $request->bearerToken();
        $result = $this->service->exportPayrollData(
            $request->start_date,
            $request->end_date,
            $token
        );

        $statusCode = $result['success'] ? 200 : 500;
        return response()->json($result, $statusCode);
    }

    /**
     * GET /api/v1/payroll/export-csv
     * Ekspor data kehadiran dan cuti langsung sebagai file CSV.
     */
    public function exportCsv(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $token = $request->bearerToken();
        $result = $this->service->exportPayrollData(
            $request->start_date,
            $request->end_date,
            $token
        );

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        $csvData = $this->generateCsv($result['data']);
        $filename = "payroll_export_{$request->start_date}_to_{$request->end_date}.csv";

        return response($csvData, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Generate CSV string dari data payroll.
     */
    private function generateCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Header CSV
        fputcsv($output, [
            'Employee ID',
            'Nama Karyawan',
            'Department',
            'Posisi',
            'Total Hadir',
            'Total Terlambat',
            'Total Alpha',
            'Total Cuti',
            'Periode Mulai',
            'Periode Selesai',
        ]);

        // Isi data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['employee_id'],
                $row['employee_name'],
                $row['department'],
                $row['position'],
                $row['total_hadir'],
                $row['total_terlambat'],
                $row['total_alpha'],
                $row['total_cuti'],
                $row['period_start'],
                $row['period_end'],
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
