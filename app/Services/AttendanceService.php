<?php

namespace App\Services;

use App\Repositories\AttendanceRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Cache TTL in seconds (5 menit).
     * Data kehadiran jarang berubah dalam hitungan menit,
     * sehingga caching 5 menit aman dan sangat efektif.
     */
    const CACHE_TTL = 300;

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

        // Invalidasi cache setelah write — data kehadiran berubah
        $this->invalidateCache($employeeId);

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

        // Invalidasi cache setelah write — data kehadiran berubah
        $this->invalidateCache($employeeId);

        return [
            'success' => true,
            'message' => "Clock-out berhasil. Total jam kerja: {$workHours} jam.",
            'data' => $attendance,
        ];
    }

    /**
     * Get attendance report for a specific employee within a custom date range.
     *
     * OPTIMASI:
     * - SEBELUM: 2 query identik (getByEmployeeAndDateRange + getEmployeeSummary yang memanggil getByEmployeeAndDateRange lagi)
     * - SESUDAH: 2 query BERBEDA (getByEmployeeAndDateRange untuk records + SQL aggregation untuk summary)
     * - Summary kini dihitung langsung di SQL menggunakan SUM(CASE WHEN), bukan di PHP
     * - Redis cache untuk menghindari query berulang pada data yang sama
     *
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getAttendanceReport(int $employeeId, string $startDate, string $endDate): array
    {
        $cacheKey = "attendance_report:{$employeeId}:{$startDate}:{$endDate}";

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $lock = Cache::lock("lock_{$cacheKey}", 30);

        try {
            if ($lock->block(30)) {
                return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($employeeId, $startDate, $endDate) {
                    // Query 1: Ambil records untuk detail list
                    $records = $this->attendanceRepository->getByEmployeeAndDateRange($employeeId, $startDate, $endDate);

                    // Query 2: Summary via SQL aggregation (BUKAN duplikat dari query 1)
                    $summary = $this->attendanceRepository->getEmployeeSummary($employeeId, $startDate, $endDate);

                    return [
                        'employee_id' => $employeeId,
                        'period' => [
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                        ],
                        'summary' => $summary,
                        'records' => $records->toArray(),
                    ];
                });
            }
        } finally {
            $lock?->release();
        }

        throw new \Exception("Server too busy to generate attendance report.");
    }

    /**
     * Get monthly attendance report for a specific employee.
     * Cached dengan key berdasarkan employee + month + year.
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
     * Cached karena ini adalah endpoint paling berat (semua karyawan sekaligus).
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getAllEmployeesReport(string $startDate, string $endDate): array
    {
        $cacheKey = "attendance_all_report:{$startDate}:{$endDate}";

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $lock = Cache::lock("lock_{$cacheKey}", 60); // 60s lock for heavy HR report

        try {
            if ($lock->block(60)) {
                return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate, $endDate) {
                    $records = $this->attendanceRepository->getAllEmployeesReport($startDate, $endDate);

                    // Group records by employee
                    $grouped = $records->groupBy('employee_id')->map(function ($employeeRecords) use ($startDate, $endDate) {
                        $employee = $employeeRecords->first()->employee;

                        return [
                            'employee' => $employee->toArray(), // prevent issues if manipulated
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
                            'records' => $employeeRecords->toArray(),
                        ];
                    });

                    return [
                        'period' => [
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                        ],
                        'total_employees' => $grouped->count(),
                        'data' => $grouped->values()->all(),
                    ];
                });
            }
        } finally {
            $lock?->release();
        }

        throw new \Exception("Server too busy to generate all employees report.");
    }

    /**
     * Invalidasi semua cache yang terkait dengan employee tertentu.
     * Dipanggil setelah clockIn/clockOut agar data terbaru langsung tersedia.
     *
     * Menggunakan pendekatan tag-less: invalidasi cache bulan ini
     * karena clock-in/out selalu terjadi pada hari ini (bulan berjalan).
     *
     * @param int $employeeId
     * @return void
     */
    private function invalidateCache(int $employeeId): void
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();
        $today = $now->toDateString();

        // Invalidasi cache report individu (ini ringan, jadi aman di-invalidasi realtime)
        Cache::forget("attendance_report:{$employeeId}:{$startOfMonth}:{$endOfMonth}");
        Cache::forget("attendance_report:{$employeeId}:{$startOfMonth}:{$today}");

        // CATATAN: Cache `attendance_all_report` sengaja TIDAK di-invalidasi di sini.
        // Endpoint report HR sangat berat. Jika di-invalidasi setiap ada user clock-in, 
        // sistem akan terkena Cache Stampede saat beban tinggi (jam sibuk masuk/pulang).
        // HR report akan menggunakan natural TTL (5 menit).
    }
}
