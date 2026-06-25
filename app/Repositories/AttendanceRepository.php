<?php

namespace App\Repositories;

use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceRepository
{
    /**
     * Find attendance record for an employee on a specific date.
     * Select optimization: hanya ambil kolom yang dibutuhkan untuk clock-in/out check.
     *
     * @param int $employeeId
     * @param string $date
     * @return Attendance|null
     */
    public function findByEmployeeAndDate(int $employeeId, string $date): ?Attendance
    {
        return Attendance::select('id', 'employee_id', 'date', 'clock_in', 'clock_out', 'status', 'late_minutes', 'work_hours')
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->first();
    }

    /**
     * Create or update clock-in record.
     *
     * @param int $employeeId
     * @param string $date
     * @param string $clockIn
     * @param string $status
     * @param int $lateMinutes
     * @return Attendance
     */
    public function clockIn(int $employeeId, string $date, string $clockIn, string $status, int $lateMinutes): Attendance
    {
        return Attendance::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'date' => $date,
            ],
            [
                'clock_in' => $clockIn,
                'status' => $status,
                'late_minutes' => $lateMinutes,
            ]
        );
    }

    /**
     * Update clock-out and calculate work hours.
     *
     * @param Attendance $attendance
     * @param string $clockOut
     * @param float $workHours
     * @return Attendance
     */
    public function clockOut(Attendance $attendance, string $clockOut, float $workHours): Attendance
    {
        $attendance->update([
            'clock_out' => $clockOut,
            'work_hours' => $workHours,
        ]);

        return $attendance;
    }

    /**
     * Get attendance records for an employee within a date range.
     * Select optimization: hanya ambil kolom yang dibutuhkan untuk tampilan list.
     *
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByEmployeeAndDateRange(int $employeeId, string $startDate, string $endDate)
    {
        return Attendance::select('id', 'employee_id', 'date', 'clock_in', 'clock_out', 'status', 'late_minutes', 'work_hours', 'notes')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Get monthly attendance report for a specific employee.
     *
     * @param int $employeeId
     * @param int $month
     * @param int $year
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMonthlyReport(int $employeeId, int $month, int $year)
    {
        return Attendance::select('id', 'employee_id', 'date', 'clock_in', 'clock_out', 'status', 'late_minutes', 'work_hours', 'notes')
            ->where('employee_id', $employeeId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Get attendance report for all employees within a date range.
     * Includes employee data for reporting.
     * Select optimization: hanya ambil kolom employee yang dibutuhkan.
     *
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllEmployeesReport(string $startDate, string $endDate)
    {
        return Attendance::select('id', 'employee_id', 'date', 'clock_in', 'clock_out', 'status', 'late_minutes', 'work_hours', 'notes')
            ->with('employee:id,name,email,department,position')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('employee_id')
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Get attendance summary statistics using SQL aggregation.
     *
     * OPTIMASI: Menggantikan pendekatan lama yang:
     * 1. Mengambil SEMUA record dari DB ke PHP (query duplikat dengan getByEmployeeAndDateRange)
     * 2. Lalu menghitung summary menggunakan Collection->where()->count() di PHP
     *
     * Pendekatan baru:
     * - 1 query SQL dengan SUM(CASE WHEN ...) — menghitung langsung di database
     * - Tidak perlu transfer seluruh record ke PHP
     * - Memanfaatkan composite index (employee_id, date, status) sebagai covering index
     *
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getEmployeeSummary(int $employeeId, string $startDate, string $endDate): array
    {
        $summary = DB::table('attendances')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw("
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as `leave`,
                SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_day,
                COALESCE(SUM(late_minutes), 0) as total_late_minutes,
                COALESCE(SUM(work_hours), 0) as total_work_hours
            ")
            ->first();

        return [
            'total_records' => (int) ($summary->total_records ?? 0),
            'present' => (int) ($summary->present ?? 0),
            'late' => (int) ($summary->late ?? 0),
            'absent' => (int) ($summary->absent ?? 0),
            'leave' => (int) ($summary->leave ?? 0),
            'half_day' => (int) ($summary->half_day ?? 0),
            'total_late_minutes' => (int) ($summary->total_late_minutes ?? 0),
            'total_work_hours' => (float) ($summary->total_work_hours ?? 0),
        ];
    }
}
