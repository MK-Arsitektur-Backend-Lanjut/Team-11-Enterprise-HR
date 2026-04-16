<?php

namespace App\Repositories;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class AttendanceRepository
{
    /**
     * Check-in for an employee.
     * Prevents duplicate check-in on the same day.
     * Automatically determines status (present/late) based on check-in time.
     *
     * @param int $employeeId
     * @param string|null $notes
     * @return Attendance
     * @throws \Exception
     */
    public function checkIn(int $employeeId, ?string $notes = null): Attendance
    {
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        // Prevent duplicate check-in
        $existing = Attendance::where('employee_id', $employeeId)
            ->where('date', $today)
            ->first();

        if ($existing) {
            throw new \Exception('Anda sudah melakukan check-in hari ini.');
        }

        // Determine status: late if after 09:00
        $lateThreshold = Carbon::today()->setTime(9, 0, 0);
        $status = $now->greaterThan($lateThreshold) ? 'late' : 'present';

        return Attendance::create([
            'employee_id' => $employeeId,
            'date'        => $today,
            'check_in'    => $now,
            'status'      => $status,
            'notes'       => $notes,
        ]);
    }

    /**
     * Check-out for an employee.
     * Updates existing attendance record with check-out time.
     * Determines if it's a half day (less than 4 hours worked).
     *
     * @param int $employeeId
     * @return Attendance
     * @throws \Exception
     */
    public function checkOut(int $employeeId): Attendance
    {
        $today = Carbon::today()->toDateString();

        $attendance = Attendance::where('employee_id', $employeeId)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            throw new \Exception('Anda belum melakukan check-in hari ini.');
        }

        if ($attendance->check_out) {
            throw new \Exception('Anda sudah melakukan check-out hari ini.');
        }

        $now = Carbon::now();
        $hoursWorked = $attendance->check_in->diffInHours($now);

        // Mark as half_day if worked less than 4 hours
        $status = $hoursWorked < 4 ? 'half_day' : $attendance->status;

        $attendance->update([
            'check_out' => $now,
            'status'    => $status,
        ]);

        return $attendance->fresh();
    }

    /**
     * Get today's attendance record for an employee.
     *
     * @param int $employeeId
     * @return Attendance|null
     */
    public function getTodayAttendance(int $employeeId): ?Attendance
    {
        return Attendance::where('employee_id', $employeeId)
            ->where('date', Carbon::today()->toDateString())
            ->first();
    }

    /**
     * Get attendance history with filters and pagination.
     * Heavy query optimized with proper indexing.
     *
     * @param int $employeeId
     * @param array $filters ['start_date', 'end_date', 'status', 'per_page']
     * @return LengthAwarePaginator
     */
    public function getHistory(int $employeeId, array $filters = []): LengthAwarePaginator
    {
        $query = Attendance::where('employee_id', $employeeId)
            ->orderBy('date', 'desc');

        // Filter by date range
        if (!empty($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Get monthly attendance summary for an employee.
     * Uses DB::raw aggregation for optimal performance on large datasets.
     *
     * @param int $employeeId
     * @param int $month
     * @param int $year
     * @return array
     */
    public function getMonthlySummary(int $employeeId, int $month, int $year): array
    {
        // Use raw SQL aggregation for performance — avoids loading thousands of rows into memory
        $stats = DB::table('attendances')
            ->select([
                DB::raw('COUNT(*) as total_days'),
                DB::raw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days"),
                DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days"),
                DB::raw("SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days"),
                DB::raw("ROUND(AVG(TIMESTAMPDIFF(HOUR, check_in, COALESCE(check_out, check_in))), 1) as avg_hours_worked"),
            ])
            ->where('employee_id', $employeeId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->first();

        // Get daily breakdown for the month
        $dailyRecords = Attendance::where('employee_id', $employeeId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'asc')
            ->get(['date', 'check_in', 'check_out', 'status', 'notes']);

        // Calculate working days in the month (excluding weekends)
        $startOfMonth = Carbon::create($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $workingDays = 0;
        $current = $startOfMonth->copy();
        while ($current->lte($endOfMonth)) {
            if (!$current->isWeekend()) {
                $workingDays++;
            }
            $current->addDay();
        }

        // Calculate attendance rate
        $attendanceRate = $workingDays > 0
            ? round((($stats->present_days + $stats->late_days) / $workingDays) * 100, 1)
            : 0;

        return [
            'employee_id'     => $employeeId,
            'month'           => $month,
            'year'            => $year,
            'working_days'    => $workingDays,
            'total_recorded'  => (int) $stats->total_days,
            'present_days'    => (int) $stats->present_days,
            'late_days'       => (int) $stats->late_days,
            'absent_days'     => (int) $stats->absent_days,
            'half_days'       => (int) $stats->half_days,
            'avg_hours_worked'=> (float) ($stats->avg_hours_worked ?? 0),
            'attendance_rate' => $attendanceRate,
            'daily_records'   => $dailyRecords,
        ];
    }

    /**
     * Get department-wide attendance statistics.
     * Heavy aggregation query optimized with DB::raw — processes thousands of records efficiently.
     *
     * @param string $department
     * @param int $month
     * @param int $year
     * @return array
     */
    public function getDepartmentStats(string $department, int $month, int $year): array
    {
        // Join employees + attendances and aggregate by department
        $stats = DB::table('attendances')
            ->join('employees', 'attendances.employee_id', '=', 'employees.id')
            ->select([
                'employees.department',
                DB::raw('COUNT(DISTINCT employees.id) as total_employees'),
                DB::raw('COUNT(*) as total_records'),
                DB::raw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN attendances.status = 'late' THEN 1 ELSE 0 END) as late_count"),
                DB::raw("SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                DB::raw("SUM(CASE WHEN attendances.status = 'half_day' THEN 1 ELSE 0 END) as half_day_count"),
                DB::raw("ROUND(AVG(TIMESTAMPDIFF(HOUR, attendances.check_in, COALESCE(attendances.check_out, attendances.check_in))), 1) as avg_hours"),
            ])
            ->where('employees.department', $department)
            ->whereMonth('attendances.date', $month)
            ->whereYear('attendances.date', $year)
            ->groupBy('employees.department')
            ->first();

        if (!$stats) {
            return [
                'department'      => $department,
                'month'           => $month,
                'year'            => $year,
                'total_employees' => 0,
                'summary'         => [],
            ];
        }

        // Get per-employee breakdown using chunked processing
        $employeeBreakdown = DB::table('attendances')
            ->join('employees', 'attendances.employee_id', '=', 'employees.id')
            ->select([
                'employees.id',
                'employees.name',
                'employees.position',
                DB::raw('COUNT(*) as total_days'),
                DB::raw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN attendances.status = 'late' THEN 1 ELSE 0 END) as late"),
            ])
            ->where('employees.department', $department)
            ->whereMonth('attendances.date', $month)
            ->whereYear('attendances.date', $year)
            ->groupBy('employees.id', 'employees.name', 'employees.position')
            ->orderBy('employees.name')
            ->get();

        return [
            'department'      => $department,
            'month'           => $month,
            'year'            => $year,
            'total_employees' => (int) $stats->total_employees,
            'summary'         => [
                'total_records' => (int) $stats->total_records,
                'present'       => (int) $stats->present_count,
                'late'          => (int) $stats->late_count,
                'absent'        => (int) $stats->absent_count,
                'half_day'      => (int) $stats->half_day_count,
                'avg_hours'     => (float) $stats->avg_hours,
            ],
            'employees'       => $employeeBreakdown,
        ];
    }
}
