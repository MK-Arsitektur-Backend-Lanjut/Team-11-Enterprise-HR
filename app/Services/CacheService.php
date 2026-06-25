<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CacheService
{
    /**
     * Default cache TTL in minutes.
     */
    const DEFAULT_TTL = 10;
    const SHORT_TTL = 3;
    const MEDIUM_TTL = 5;

    /**
     * Invalidate all cache related to a specific employee.
     *
     * @param int $employeeId
     * @return void
     */
    public static function invalidateEmployee(int $employeeId): void
    {
        $keys = [
            "employee:{$employeeId}",
            "employee:leave_balance:{$employeeId}",
            "employee:subordinates:{$employeeId}",
            "employee:hierarchy:{$employeeId}",
            "subordinate:requests:{$employeeId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Also invalidate statistics since employee data changed
        Cache::forget('employee:statistics');

        Log::debug("CacheService: Invalidated employee cache for ID {$employeeId}");
    }

    /**
     * Invalidate attendance-related cache for a specific employee and date.
     *
     * @param int $employeeId
     * @param string $date
     * @return void
     */
    public static function invalidateAttendance(int $employeeId, string $date): void
    {
        $month = Carbon::parse($date)->month;
        $year = Carbon::parse($date)->year;

        $keys = [
            "attendance:monthly:{$employeeId}:{$year}:{$month}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Invalidate "all employees" report cache (pattern-based)
        // Since we can't do pattern delete with all drivers, we use tagged cache or specific keys
        Cache::forget("attendance:all");

        Log::debug("CacheService: Invalidated attendance cache for employee {$employeeId} on {$date}");
    }

    /**
     * Invalidate all leave-related cache for a specific employee.
     *
     * @param int $employeeId
     * @return void
     */
    public static function invalidateLeave(int $employeeId): void
    {
        $keys = [
            "leaves:employee:{$employeeId}",
            "employee:leave_balance:{$employeeId}",
            "subordinate:requests:{$employeeId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Invalidate aggregated caches
        Cache::forget('leaves:all');

        Log::debug("CacheService: Invalidated leave cache for employee {$employeeId}");
    }

    /**
     * Invalidate all leave-related caches (used after sync operations).
     *
     * @return void
     */
    public static function invalidateAllLeaves(): void
    {
        Cache::forget('leaves:all');

        Log::debug("CacheService: Invalidated all leaves cache");
    }

    /**
     * Flush the entire cache store.
     * Use with caution — only for full sync/reset scenarios.
     *
     * @return void
     */
    public static function flushAll(): void
    {
        Cache::flush();

        Log::info("CacheService: Flushed entire cache store");
    }
}
