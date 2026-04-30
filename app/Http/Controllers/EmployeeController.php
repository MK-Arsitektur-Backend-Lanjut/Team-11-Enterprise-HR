<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    private $employeeRepository;

    public function __construct(\App\Repositories\EmployeeRepository $employeeRepository)
    {
        $this->employeeRepository = $employeeRepository;
    }

    public function profile(Request $request, $id)
    {
        $profile = $this->employeeRepository->getEmployeeProfile($id);

        return response()->json([
            'success' => true,
            'data' => $profile
        ]);
    }

    public function hierarchy($id)
    {
        $hierarchy = $this->employeeRepository->getHierarchy($id);

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => collect($hierarchy)->except(['manager', 'subordinates']),
                'manager' => $hierarchy->manager,
                'subordinates' => $hierarchy->subordinates
            ]
        ]);
    }

    public function updateLeaveBalance(Request $request, $id)
    {
        $request->validate([
            'leave_balance' => 'required|integer|min:0'
        ]);

        $employee = $this->employeeRepository->updateLeaveBalance($id, $request->leave_balance);

        return response()->json([
            'success' => true,
            'message' => 'Leave balance updated successfully',
            'data' => $employee
        ]);
    }
}
