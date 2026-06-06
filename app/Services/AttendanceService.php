<?php

namespace App\Services;

use App\Repositories\AttendanceRepository;
use Illuminate\Support\Carbon;

class AttendanceService
{
    /**
     * Default work start time (08:00).
     */
    const WORK_START_TIME = '08:00';

    /**
     * Default work end time (17:00).
     */
    const WORK_END_TIME = '17:00';

    protected AttendanceRepository $attendanceRepository;

    public function __construct(AttendanceRepository $attendanceRepository)
    {
        $this->attendanceRepository = $attendanceRepository;
    }

    /**
     * Process employee clock-in.
     * Determines status (present/late) based on work start time (08:00).
     *
     * @param int $employeeId
     * @return array
     */
    public function clockIn(int $employeeId): array
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        // Check if already clocked in today
        $existing = $this->attendanceRepository->findByEmployeeAndDate($employeeId, $today);
        if ($existing && $existing->clock_in) {
            return [
                'success' => false,
                'message' => 'Anda sudah melakukan clock-in hari ini pada ' . $existing->clock_in,
                'data' => $existing,
            ];
        }

        // Calculate late minutes
        $workStart = Carbon::createFromFormat('H:i', self::WORK_START_TIME);
        $clockInTime = Carbon::createFromFormat('H:i:s', $currentTime);
        $lateMinutes = 0;
        $status = 'present';

        if ($clockInTime->gt($workStart)) {
            $lateMinutes = abs($clockInTime->diffInMinutes($workStart));
            $status = 'late';
        }

        $attendance = $this->attendanceRepository->clockIn(
            $employeeId,
            $today,
            $currentTime,
            $status,
            $lateMinutes
        );

        return [
            'success' => true,
            'message' => $status === 'late'
                ? "Clock-in berhasil. Anda terlambat {$lateMinutes} menit."
                : 'Clock-in berhasil. Anda tepat waktu.',
            'data' => $attendance,
        ];
    }

    /**
     * Process employee clock-out.
     * Calculates total work hours.
     *
     * @param int $employeeId
     * @return array
     */
    public function clockOut(int $employeeId): array
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        // Check if clocked in today
        $attendance = $this->attendanceRepository->findByEmployeeAndDate($employeeId, $today);
        if (!$attendance || !$attendance->clock_in) {
            return [
                'success' => false,
                'message' => 'Anda belum melakukan clock-in hari ini.',
                'data' => null,
            ];
        }

        if ($attendance->clock_out) {
            return [
                'success' => false,
                'message' => 'Anda sudah melakukan clock-out hari ini pada ' . $attendance->clock_out,
                'data' => $attendance,
            ];
        }

        // Calculate work hours
        $clockIn = Carbon::createFromFormat('H:i:s', $attendance->clock_in);
        $clockOut = Carbon::createFromFormat('H:i:s', $currentTime);
        $workHours = round(abs($clockOut->diffInMinutes($clockIn)) / 60, 2);

        $attendance = $this->attendanceRepository->clockOut($attendance, $currentTime, $workHours);

        return [
            'success' => true,
            'message' => "Clock-out berhasil. Total jam kerja: {$workHours} jam.",
            'data' => $attendance,
        ];
    }

    /**
     * Get attendance report for a specific employee within a custom date range.
     *
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getAttendanceReport(int $employeeId, string $startDate, string $endDate): array
    {
        $records = $this->attendanceRepository->getByEmployeeAndDateRange($employeeId, $startDate, $endDate);
        $summary = $this->attendanceRepository->getEmployeeSummary($employeeId, $startDate, $endDate);

        return [
            'employee_id' => $employeeId,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => $summary,
            'records' => $records,
        ];
    }

    /**
     * Get monthly attendance report for a specific employee.
     *
     * @param int $employeeId
     * @param int $month
     * @param int $year
     * @return array
     */
    public function getMonthlyReport(int $employeeId, int $month, int $year): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        return $this->getAttendanceReport($employeeId, $startDate, $endDate);
    }

    /**
     * Get attendance report for all employees within a date range.
     * This is used by HR to view the full report.
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getAllEmployeesReport(string $startDate, string $endDate): array
    {
        $records = $this->attendanceRepository->getAllEmployeesReport($startDate, $endDate);

        // Group records by employee
        $grouped = $records->groupBy('employee_id')->map(function ($employeeRecords) use ($startDate, $endDate) {
            $employee = $employeeRecords->first()->employee;

            return [
                'employee' => $employee,
                'summary' => [
                    'total_records' => $employeeRecords->count(),
                    'present' => $employeeRecords->where('status', 'present')->count(),
                    'late' => $employeeRecords->where('status', 'late')->count(),
                    'absent' => $employeeRecords->where('status', 'absent')->count(),
                    'leave' => $employeeRecords->where('status', 'leave')->count(),
                    'half_day' => $employeeRecords->where('status', 'half_day')->count(),
                    'total_late_minutes' => $employeeRecords->sum('late_minutes'),
                    'total_work_hours' => (float) $employeeRecords->sum('work_hours'),
                ],
                'records' => $employeeRecords,
            ];
        });

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'total_employees' => $grouped->count(),
            'data' => $grouped->values(),
        ];
    }
}
