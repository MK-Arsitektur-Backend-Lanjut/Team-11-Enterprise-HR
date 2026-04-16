<?php

namespace App\Repositories;

use App\Models\Employee;

class EmployeeRepository
{
    public function getEmployeeProfile($id)
    {
        return Employee::with('manager')->findOrFail($id);
    }

    public function getManager($id)
    {
        $employee = Employee::findOrFail($id);
        return $employee->manager;
    }

    public function getSubordinates($id)
    {
        return Employee::where('manager_id', $id)->get();
    }

    public function getHierarchy($id)
    {
        return Employee::with(['manager', 'subordinates'])->findOrFail($id);
    }

    public function updateLeaveBalance($id, $amount)
    {
        $employee = Employee::findOrFail($id);
        $employee->leave_balance = $amount;
        $employee->save();

        return $employee;
    }
}
