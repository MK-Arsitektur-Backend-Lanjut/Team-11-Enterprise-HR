<?php

namespace App\Repositories;

use App\Models\Leave;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class LeaveRepository
{
    /**
     * Apply for leave.
     * Automatically calculates total_days (excluding weekends).
     * Validates leave balance before submission.
     *
     * @param int $employeeId
     * @param array $data ['type', 'start_date', 'end_date', 'reason']
     * @return Leave
     * @throws \Exception
     */
    public function applyLeave(int $employeeId, array $data): Leave
    {
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        // Validate date range
        if ($endDate->lt($startDate)) {
            throw new \Exception('Tanggal selesai harus setelah tanggal mulai.');
        }

        // Calculate working days (exclude weekends)
        $totalDays = $this->calculateWorkingDays($startDate, $endDate);

        if ($totalDays <= 0) {
            throw new \Exception('Periode cuti tidak mengandung hari kerja.');
        }

        // Check leave balance (skip for unpaid leave)
        if ($data['type'] !== 'unpaid') {
            $employee = Employee::findOrFail($employeeId);
            if ($employee->leave_balance < $totalDays) {
                throw new \Exception(
                    "Sisa cuti tidak mencukupi. Sisa: {$employee->leave_balance} hari, dibutuhkan: {$totalDays} hari."
                );
            }
        }

        // Check for overlapping leave requests
        $overlapping = Leave::where('employee_id', $employeeId)
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($overlapping) {
            throw new \Exception('Terdapat pengajuan cuti yang tumpang tindih pada tanggal tersebut.');
        }

        return Leave::create([
            'employee_id' => $employeeId,
            'type'        => $data['type'],
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'total_days'  => $totalDays,
            'reason'      => $data['reason'],
            'status'      => 'pending',
        ]);
    }

    /**
     * Approve a leave request.
     * Deducts leave balance from the employee (within a DB transaction).
     *
     * @param int $leaveId
     * @param int $approverId
     * @return Leave
     * @throws \Exception
     */
    public function approveLeave(int $leaveId, int $approverId): Leave
    {
        return DB::transaction(function () use ($leaveId, $approverId) {
            $leave = Leave::where('id', $leaveId)
                ->where('status', 'pending')
                ->firstOrFail();

            // Update leave status
            $leave->update([
                'status'      => 'approved',
                'approved_by' => $approverId,
                'approved_at' => Carbon::now(),
            ]);

            // Deduct leave balance (skip for unpaid)
            if ($leave->type !== 'unpaid') {
                $employee = Employee::findOrFail($leave->employee_id);
                $newBalance = max(0, $employee->leave_balance - $leave->total_days);
                $employee->update(['leave_balance' => $newBalance]);
            }

            return $leave->fresh()->load(['employee', 'approver']);
        });
    }

    /**
     * Reject a leave request.
     *
     * @param int $leaveId
     * @param int $approverId
     * @return Leave
     */
    public function rejectLeave(int $leaveId, int $approverId): Leave
    {
        $leave = Leave::where('id', $leaveId)
            ->where('status', 'pending')
            ->firstOrFail();

        $leave->update([
            'status'      => 'rejected',
            'approved_by' => $approverId,
            'approved_at' => Carbon::now(),
        ]);

        return $leave->fresh()->load(['employee', 'approver']);
    }

    /**
     * Get leave history with filters and pagination.
     *
     * @param int $employeeId
     * @param array $filters ['type', 'status', 'start_date', 'end_date', 'per_page']
     * @return LengthAwarePaginator
     */
    public function getLeaveHistory(int $employeeId, array $filters = []): LengthAwarePaginator
    {
        $query = Leave::where('employee_id', $employeeId)
            ->with('approver')
            ->orderBy('created_at', 'desc');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('start_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('end_date', '<=', $filters['end_date']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Get remaining leave balance for an employee.
     *
     * @param int $employeeId
     * @return array
     */
    public function getLeaveBalance(int $employeeId): array
    {
        $employee = Employee::findOrFail($employeeId);

        // Get used leave days by type (approved only)
        $usedByType = DB::table('leaves')
            ->select([
                'type',
                DB::raw('SUM(total_days) as used_days'),
                DB::raw('COUNT(*) as total_requests'),
            ])
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereYear('start_date', Carbon::now()->year)
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Get pending requests count
        $pendingCount = Leave::where('employee_id', $employeeId)
            ->where('status', 'pending')
            ->count();

        return [
            'employee_id'      => $employeeId,
            'employee_name'    => $employee->name,
            'total_balance'    => $employee->leave_balance,
            'used_this_year'   => $usedByType->sum('used_days'),
            'pending_requests' => $pendingCount,
            'breakdown'        => [
                'annual'   => (int) ($usedByType->get('annual')->used_days ?? 0),
                'sick'     => (int) ($usedByType->get('sick')->used_days ?? 0),
                'personal' => (int) ($usedByType->get('personal')->used_days ?? 0),
                'unpaid'   => (int) ($usedByType->get('unpaid')->used_days ?? 0),
            ],
        ];
    }

    /**
     * Calculate working days between two dates (excluding weekends).
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return int
     */
    private function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $days = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            if (!$current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }
}
