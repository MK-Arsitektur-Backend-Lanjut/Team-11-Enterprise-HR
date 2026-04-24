<?php

namespace App\Repositories;

use App\Models\Attendance;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class AttendanceRepository
{
    /**
     * Buat record attendance baru (check-in).
     */
    public function create(array $data): Attendance
    {
        return Attendance::create($data);
    }

    /**
     * Cari attendance berdasarkan user_id dan tanggal.
     */
    public function findByUserAndDate(int $userId, string $date): ?Attendance
    {
        return Attendance::where('user_id', $userId)
            ->where('date', $date)
            ->first();
    }

    /**
     * Update record attendance (check-out, status, notes).
     */
    public function update(Attendance $attendance, array $data): Attendance
    {
        $attendance->update($data);
        return $attendance->fresh();
    }

    /**
     * Ambil riwayat attendance user dengan pagination.
     */
    public function getHistoryByUser(int $userId, ?string $startDate = null, ?string $endDate = null, int $perPage = 15): mixed
    {
        $query = Attendance::where('user_id', $userId)
            ->orderBy('date', 'desc');

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return $query->paginate($perPage);
    }

    /**
     * Ambil statistik kehadiran per department.
     * Menggunakan join ke users untuk filter.
     */
    public function getDepartmentStats(string $date, array $userIds): array
    {
        if (empty($userIds)) {
            return [
                'total_employees' => 0,
                'hadir'           => 0,
                'terlambat'       => 0,
                'alpha'           => 0,
                'izin'            => 0,
                'cuti'            => 0,
            ];
        }

        $attendances = Attendance::whereIn('user_id', $userIds)
            ->where('date', $date)
            ->get();

        return [
            'total_employees' => count($userIds),
            'hadir'           => $attendances->where('status', 'hadir')->count(),
            'terlambat'       => $attendances->where('status', 'terlambat')->count(),
            'alpha'           => count($userIds) - $attendances->count(),
            'izin'            => $attendances->where('status', 'izin')->count(),
            'cuti'            => $attendances->where('status', 'cuti')->count(),
        ];
    }

    /**
     * Ambil semua attendance dalam rentang tanggal untuk batch user.
     * Digunakan untuk keperluan payroll export.
     */
    public function getAttendancesByDateRange(array $userIds, string $startDate, string $endDate): Collection
    {
        return Attendance::whereIn('user_id', $userIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('user_id')
            ->orderBy('date')
            ->get();
    }

    /**
     * Ambil semua attendance berdasarkan user_id dalam rentang tanggal.
     */
    public function getByUserAndDateRange(int $userId, string $startDate, string $endDate): Collection
    {
        return Attendance::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();
    }
}
