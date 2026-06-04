<?php

namespace App\Repositories;

use App\Models\Employee;

class EmployeeRepository
{
    public function getPaginatedEmployees($perPage, $filters = [])
    {
        $query = Employee::query()->with('manager:id,name,position');

        if (isset($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (isset($filters['position'])) {
            $query->where('position', $filters['position']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function getEmployeeById($id)
    {
        return Employee::with(['manager:id,name,position,department', 'subordinates:id,name,position,department'])->find($id);
    }

    public function createEmployee(array $data)
    {
        return Employee::create($data);
    }

    public function updateEmployee($id, array $data)
    {
        $employee = Employee::find($id);
        if ($employee) {
            $employee->update($data);
            return $employee;
        }
        return null;
    }

    public function deleteEmployee($id)
    {
        $employee = Employee::find($id);
        if ($employee) {
            $employee->delete();
            return true;
        }
        return false;
    }

    public function getSubordinates($managerId)
    {
        return Employee::where('manager_id', $managerId)
            ->select('id', 'name', 'email', 'position', 'department')
            ->get();
    }

    public function getEmployeeHierarchy($id)
    {
        return Employee::with(['manager', 'subordinates'])->find($id);
    }

    public function updateLeaveBalance($id, $leaveBalance)
    {
        $employee = Employee::find($id);
        if ($employee) {
            $employee->leave_balance = $leaveBalance;
            $employee->save();
            return $employee;
        }
        return null;
    }

    public function getStatistics()
    {
        return [
            'total_employees' => Employee::count(),
            'by_position' => Employee::select('position')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('position')
                ->get(),
            'by_department' => Employee::select('department')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('department')
                ->orderBy('count', 'desc')
                ->get(),
            'average_leave_balance' => Employee::avg('leave_balance'),
        ];
    }
}
