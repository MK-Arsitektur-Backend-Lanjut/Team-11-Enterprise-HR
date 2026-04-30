<?php

namespace App\Services;

use App\Repositories\AttendanceRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceService
{
    private AttendanceRepository $repository;
    private string $employeeApiUrl;
    private string $approvalApiUrl;

    public function __construct(AttendanceRepository $repository)
    {
        $this->repository = $repository;
        $this->employeeApiUrl = rtrim(config('services.employee.url'), '/');
        $this->approvalApiUrl = rtrim(config('services.approval.url'), '/');
    }

    /**
     * Proses check-in karyawan.
     * Menentukan status hadir/terlambat berdasarkan jam masuk.
     */
    public function checkIn(int $userId, ?string $notes = null): array
    {
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        // Cek apakah sudah check-in hari ini
        $existing = $this->repository->findByUserAndDate($userId, $today);
        if ($existing) {
            return [
                'success' => false,
                'message' => 'Anda sudah melakukan check-in hari ini.',
                'data'    => $existing,
            ];
        }

        // Tentukan status: jika check-in setelah jam 09:00, maka terlambat
        $lateThreshold = Carbon::today()->setTime(9, 0, 0);
        $status = $now->greaterThan($lateThreshold) ? 'terlambat' : 'hadir';

        $attendance = $this->repository->create([
            'user_id'  => $userId,
            'date'     => $today,
            'check_in' => $now->toTimeString(),
            'status'   => $status,
            'notes'    => $notes,
        ]);

        return [
            'success' => true,
            'message' => $status === 'terlambat'
                ? 'Check-in berhasil, tetapi Anda terlambat.'
                : 'Check-in berhasil.',
            'data'    => $attendance,
        ];
    }

    /**
     * Proses check-out karyawan.
     */
    public function checkOut(int $userId, ?string $notes = null): array
    {
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        $attendance = $this->repository->findByUserAndDate($userId, $today);

        if (!$attendance) {
            return [
                'success' => false,
                'message' => 'Anda belum melakukan check-in hari ini.',
            ];
        }

        if ($attendance->check_out) {
            return [
                'success' => false,
                'message' => 'Anda sudah melakukan check-out hari ini.',
                'data'    => $attendance,
            ];
        }

        $updateData = ['check_out' => $now->toTimeString()];
        if ($notes) {
            $updateData['notes'] = $attendance->notes
                ? $attendance->notes . ' | Checkout: ' . $notes
                : $notes;
        }

        $updated = $this->repository->update($attendance, $updateData);

        return [
            'success' => true,
            'message' => 'Check-out berhasil.',
            'data'    => $updated,
        ];
    }

    /**
     * Ambil riwayat kehadiran user.
     */
    public function getHistory(int $userId, ?string $startDate = null, ?string $endDate = null, int $perPage = 15): mixed
    {
        return $this->repository->getHistoryByUser($userId, $startDate, $endDate, $perPage);
    }



    /**
     * Ekspor data kehadiran dan cuti untuk keperluan penggajian (payroll).
     *
     * Menggabungkan:
     * 1. Data attendance dari database lokal (Modul Attendance).
     * 2. Data cuti approved dari Modul Approval (via HTTP call).
     * 3. Data profil karyawan dari Modul Employee (via HTTP call).
     *
     * Output: Array yang siap dikonversi ke CSV.
     */
    public function exportPayrollData(string $startDate, string $endDate, string $token): array
    {
        try {
            // 1. Ambil semua user yang terdaftar
            $users = \App\Models\User::whereNotNull('employee_id')->get();

            if ($users->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Tidak ada data karyawan yang terdaftar.',
                ];
            }

            $userIds = $users->pluck('id')->toArray();
            $employeeIdMap = $users->pluck('employee_id', 'id')->toArray(); // user_id => employee_id

            // 2. Ambil data attendance lokal
            $attendances = $this->repository->getAttendancesByDateRange($userIds, $startDate, $endDate);

            // 3. Ambil data cuti approved dari Modul Approval
            $leaveData = $this->fetchApprovedLeaves($startDate, $endDate, $token);

            // 4. Ambil data profil karyawan dari Modul Employee
            $employeeProfiles = $this->fetchEmployeeProfiles(array_values($employeeIdMap), $token);

            // 5. Kompilasi data payroll
            $payrollData = [];
            foreach ($users as $user) {
                $employeeId = $user->employee_id;
                $profile = $employeeProfiles[$employeeId] ?? null;

                // Hitung kehadiran
                $userAttendances = $attendances->where('user_id', $user->id);
                $totalHadir = $userAttendances->whereIn('status', ['hadir', 'terlambat'])->count();
                $totalTerlambat = $userAttendances->where('status', 'terlambat')->count();
                $totalAlpha = $userAttendances->where('status', 'alpha')->count();

                // Hitung cuti dari data Modul Approval
                $employeeLeaves = collect($leaveData)->where('employee_id', $employeeId);
                $totalCuti = 0;
                foreach ($employeeLeaves as $leave) {
                    $start = Carbon::parse($leave['start_date']);
                    $end = Carbon::parse($leave['end_date']);
                    $totalCuti += $start->diffInDays($end) + 1;
                }

                $payrollData[] = [
                    'employee_id'    => $employeeId,
                    'employee_name'  => $profile['name'] ?? $user->name,
                    'department'     => $profile['department'] ?? '-',
                    'position'       => $profile['position'] ?? '-',
                    'total_hadir'    => $totalHadir,
                    'total_terlambat' => $totalTerlambat,
                    'total_alpha'    => $totalAlpha,
                    'total_cuti'     => $totalCuti,
                    'period_start'   => $startDate,
                    'period_end'     => $endDate,
                ];
            }

            return [
                'success' => true,
                'data'    => $payrollData,
            ];
        } catch (\Exception $e) {
            Log::error("Payroll export error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengekspor data payroll: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Ambil data cuti yang sudah approved dari Modul Approval (microservice).
     */
    private function fetchApprovedLeaves(string $startDate, string $endDate, string $token): array
    {
        try {
            $response = Http::withToken($token)
                ->get("{$this->approvalApiUrl}/leaves/approved", [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                ]);

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            Log::error("Failed fetching approved leaves: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Fetch approved leaves exception: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Ambil profil karyawan dari Modul Employee (microservice).
     * Mengembalikan array yang di-index berdasarkan employee_id.
     */
    private function fetchEmployeeProfiles(array $employeeIds, string $token): array
    {
        $profiles = [];

        foreach ($employeeIds as $empId) {
            try {
                $response = Http::withToken($token)
                    ->get("{$this->employeeApiUrl}/employees/{$empId}/hierarchy");

                if ($response->successful()) {
                    $data = $response->json('data.employee');
                    if ($data) {
                        $profiles[$empId] = $data;
                    }
                }
            } catch (\Exception $e) {
                Log::error("Fetch employee profile error for ID {$empId}: " . $e->getMessage());
            }
        }

        return $profiles;
    }
}
