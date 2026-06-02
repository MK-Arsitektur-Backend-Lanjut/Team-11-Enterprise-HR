<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export attendance report to CSV.
     *
     * @param array $reportData
     * @param string $filename
     * @return StreamedResponse
     */
    public function exportAttendanceToCSV(array $reportData, string $filename = 'attendance_report.csv'): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($reportData) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Report info header
            fputcsv($handle, ['Laporan Kehadiran Karyawan']);
            fputcsv($handle, ['Periode', $reportData['period']['start_date'] . ' s/d ' . $reportData['period']['end_date']]);
            fputcsv($handle, ['Tanggal Ekspor', Carbon::now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, []); // Empty row separator

            // Check if this is an all-employees report or single employee
            if (isset($reportData['data'])) {
                // All employees report
                fputcsv($handle, ['Total Karyawan', $reportData['total_employees']]);
                fputcsv($handle, []);

                // Summary table header
                fputcsv($handle, [
                    'No',
                    'Nama Karyawan',
                    'Department',
                    'Position',
                    'Hadir',
                    'Terlambat',
                    'Absen',
                    'Cuti',
                    'Setengah Hari',
                    'Total Menit Terlambat',
                    'Total Jam Kerja',
                ]);

                $no = 1;
                foreach ($reportData['data'] as $employeeData) {
                    $emp = $employeeData['employee'];
                    $sum = $employeeData['summary'];
                    fputcsv($handle, [
                        $no++,
                        $emp['name'] ?? $emp->name ?? '',
                        $emp['department'] ?? $emp->department ?? '',
                        $emp['position'] ?? $emp->position ?? '',
                        $sum['present'],
                        $sum['late'],
                        $sum['absent'],
                        $sum['leave'],
                        $sum['half_day'],
                        $sum['total_late_minutes'],
                        $sum['total_work_hours'],
                    ]);
                }

                fputcsv($handle, []);
                fputcsv($handle, ['Detail Kehadiran Harian']);
                fputcsv($handle, []);

                // Detail records per employee
                fputcsv($handle, [
                    'Nama Karyawan',
                    'Tanggal',
                    'Clock In',
                    'Clock Out',
                    'Status',
                    'Menit Terlambat',
                    'Jam Kerja',
                    'Catatan',
                ]);

                foreach ($reportData['data'] as $employeeData) {
                    foreach ($employeeData['records'] as $record) {
                        $emp = $employeeData['employee'];
                        fputcsv($handle, [
                            $emp['name'] ?? $emp->name ?? '',
                            $record['date'] ?? $record->date ?? '',
                            $record['clock_in'] ?? $record->clock_in ?? '-',
                            $record['clock_out'] ?? $record->clock_out ?? '-',
                            $record['status'] ?? $record->status ?? '',
                            $record['late_minutes'] ?? $record->late_minutes ?? 0,
                            $record['work_hours'] ?? $record->work_hours ?? 0,
                            $record['notes'] ?? $record->notes ?? '',
                        ]);
                    }
                }
            } else {
                // Single employee report
                fputcsv($handle, ['Employee ID', $reportData['employee_id']]);

                $sum = $reportData['summary'];
                fputcsv($handle, []);
                fputcsv($handle, ['Ringkasan']);
                fputcsv($handle, ['Hadir', $sum['present']]);
                fputcsv($handle, ['Terlambat', $sum['late']]);
                fputcsv($handle, ['Absen', $sum['absent']]);
                fputcsv($handle, ['Cuti', $sum['leave']]);
                fputcsv($handle, ['Setengah Hari', $sum['half_day']]);
                fputcsv($handle, ['Total Menit Terlambat', $sum['total_late_minutes']]);
                fputcsv($handle, ['Total Jam Kerja', $sum['total_work_hours']]);
                fputcsv($handle, []);

                // Detail records
                fputcsv($handle, [
                    'Tanggal',
                    'Clock In',
                    'Clock Out',
                    'Status',
                    'Menit Terlambat',
                    'Jam Kerja',
                    'Catatan',
                ]);

                foreach ($reportData['records'] as $record) {
                    fputcsv($handle, [
                        $record['date'] ?? $record->date ?? '',
                        $record['clock_in'] ?? $record->clock_in ?? '-',
                        $record['clock_out'] ?? $record->clock_out ?? '-',
                        $record['status'] ?? $record->status ?? '',
                        $record['late_minutes'] ?? $record->late_minutes ?? 0,
                        $record['work_hours'] ?? $record->work_hours ?? 0,
                        $record['notes'] ?? $record->notes ?? '',
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export leave data for payroll to CSV.
     *
     * @param array $payrollData
     * @param string $filename
     * @return StreamedResponse
     */
    public function exportLeavePayrollToCSV(array $payrollData, string $filename = 'leave_payroll_export.csv'): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($payrollData) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Report info header
            fputcsv($handle, ['Ekspor Data Cuti untuk Penggajian']);
            fputcsv($handle, ['Periode', $payrollData['period']['start_date'] . ' s/d ' . $payrollData['period']['end_date']]);
            fputcsv($handle, ['Tanggal Ekspor', Carbon::now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, ['Total Karyawan dengan Cuti', $payrollData['total_employees_with_leave']]);
            fputcsv($handle, ['Total Hari Cuti', $payrollData['total_leave_days']]);
            fputcsv($handle, []);

            // Summary per employee
            fputcsv($handle, ['Ringkasan per Karyawan']);
            fputcsv($handle, [
                'No',
                'ID Karyawan',
                'Nama Karyawan',
                'Email',
                'Department',
                'Position',
                'Total Hari Cuti',
            ]);

            $no = 1;
            foreach ($payrollData['data'] as $empData) {
                fputcsv($handle, [
                    $no++,
                    $empData['employee_id'],
                    $empData['employee_name'],
                    $empData['email'],
                    $empData['department'],
                    $empData['position'],
                    $empData['total_leave_days'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Detail Cuti per Karyawan']);
            fputcsv($handle, [
                'Nama Karyawan',
                'Tipe Cuti',
                'Tanggal Mulai',
                'Tanggal Selesai',
                'Total Hari',
                'Alasan',
            ]);

            foreach ($payrollData['data'] as $empData) {
                foreach ($empData['leave_details'] as $detail) {
                    fputcsv($handle, [
                        $empData['employee_name'],
                        $detail['leave_type'],
                        $detail['start_date'],
                        $detail['end_date'],
                        $detail['total_days'],
                        $detail['reason'] ?? '',
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }
}
