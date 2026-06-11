<?php

namespace App\Repositories;

use App\Models\Leave;

class LeaveRepository
{
    /**
     * Create a new leave record (used during sync from Approval Service).
     *
     * @param array $data
     * @return Leave
     */
    public function create(array $data): Leave
    {
        return Leave::create($data);
    }

    /**
     * Update or create a leave record based on external_leave_id.
     * Used for syncing from Approval Workflow module.
     *
     * @param int $externalLeaveId
     * @param array $data
     * @return Leave
     */
    public function syncFromExternal(int $externalLeaveId, array $data): Leave
    {
        return Leave::updateOrCreate(
            ['external_leave_id' => $externalLeaveId],
            $data
        );
    }

    /**
     * Get leave records for an employee within a date range.
     *
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByEmployeeAndDateRange(int $employeeId, string $startDate, string $endDate)
    {
        return Leave::where('employee_id', $employeeId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->orderBy('start_date', 'asc')
            ->get();
    }

    /**
     * Get approved leaves summary for all employees within a date range.
     * Used for payroll export.
     *
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getApprovedLeavesSummary(string $startDate, string $endDate)
    {
        return Leave::with('employee:id,name,email,department,position')
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->orderBy('employee_id')
            ->orderBy('start_date', 'asc')
            ->get();
    }

    /**
     * Get all leaves for a specific employee (for personal view).
     *
     * @param int $employeeId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByEmployee(int $employeeId)
    {
        return Leave::where('employee_id', $employeeId)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Find a leave by ID.
     *
     * @param int $id
     * @return Leave|null
     */
    public function findById(int $id): ?Leave
    {
        return Leave::with('employee:id,name,email,department,position')->find($id);
    }

    /**
     * Get all leaves with optional filters.
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllWithFilters(array $filters = [])
    {
        $query = Leave::with('employee:id,name,email,department,position');

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['leave_type'])) {
            $query->where('leave_type', $filters['leave_type']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereBetween('start_date', [$filters['start_date'], $filters['end_date']])
                    ->orWhereBetween('end_date', [$filters['start_date'], $filters['end_date']]);
            });
        }

        return $query->orderBy('start_date', 'desc')->get();
    }
}
