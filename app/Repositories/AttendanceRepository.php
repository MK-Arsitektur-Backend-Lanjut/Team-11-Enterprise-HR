<?php

namespace App\Repositories;

use App\Models\Attendance;
use Illuminate\Support\Carbon;

class AttendanceRepository
{
    /**
     * Find attendance record for an employee on a specific date.
     *
     * @param int $employeeId
     * @param string $date
     * @return Attendance|null
     */
    public function findByEmployeeAndDate(int $employeeId, string $date): ?Attendance
    {
        return Attendance::where('employee_id', $employeeId)
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
     * @param string|null $notes
     * @return Attendance
     */
    public function clockIn(int $employeeId, string $date, string $clockIn, string $status, int $lateMinutes, ?string $notes = null): Attendance
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
                'notes' => $notes,
            ]
        );
    }

    /**
     * Update clock-out and calculate work hours.
     *
     * @param Attendance $attendance
     * @param string $clockOut
     * @param float $workHours
     * @param string|null $notes
     * @return Attendance
     */
    public function clockOut(Attendance $attendance, string $clockOut, float $workHours, ?string $notes = null): Attendance
    {
        $data = [
            'clock_out' => $clockOut,
            'work_hours' => $workHours,
        ];

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        $attendance->update($data);

        return $attendance;
    }

    /**
     * Get attendance records for an employee within a date range.
     *
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByEmployeeAndDateRange(int $employeeId, string $startDate, string $endDate)
    {
        return Attendance::where('employee_id', $employeeId)
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
        return Attendance::where('employee_id', $employeeId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Get attendance report for all employees within a date range.
     * Includes employee data for reporting.
     *
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllEmployeesReport(string $startDate, string $endDate)
    {
        return Attendance::with('employee:id,name,email,department,position')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('employee_id')
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Get attendance summary statistics for an employee within a date range.
     *
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getEmployeeSummary(int $employeeId, string $startDate, string $endDate): array
    {
        $records = $this->getByEmployeeAndDateRange($employeeId, $startDate, $endDate);

        return [
            'total_records' => $records->count(),
            'present' => $records->where('status', 'present')->count(),
            'late' => $records->where('status', 'late')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'leave' => $records->where('status', 'leave')->count(),
            'half_day' => $records->where('status', 'half_day')->count(),
            'total_late_minutes' => $records->sum('late_minutes'),
            'total_work_hours' => (float) $records->sum('work_hours'),
        ];
    }
}
