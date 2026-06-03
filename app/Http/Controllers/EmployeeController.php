<?php

namespace App\Http\Controllers;

use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    private $service;

    public function __construct(EmployeeService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all employees with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $filters = $request->only(['department', 'position', 'search']);
        
        $employees = $this->service->getPaginatedEmployees($perPage, $filters);

        return response()->json($employees);
    }

    /**
     * Get single employee by ID
     */
    public function show($id)
    {
        $employee = $this->service->getEmployeeById($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json($employee);
    }

    /**
     * Create new employee
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email',
            'password' => 'required|string|min:6',
            'position' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'leave_balance' => 'nullable|integer|min:0',
            'manager_id' => 'nullable|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'email', 'position', 'department', 'leave_balance', 'manager_id']);
        $data['password'] = Hash::make($request->password);
        $data['leave_balance'] = $data['leave_balance'] ?? 15;

        $employee = $this->service->createEmployee($data);

        return response()->json([
            'message' => 'Employee created successfully',
            'data' => $employee
        ], 201);
    }

    /**
     * Update employee
     */
    public function update(Request $request, $id)
    {
        $employee = $this->service->getEmployeeById($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:employees,email,' . $id,
            'password' => 'sometimes|string|min:6',
            'position' => 'sometimes|string|max:100',
            'department' => 'sometimes|string|max:100',
            'leave_balance' => 'sometimes|integer|min:0',
            'manager_id' => 'nullable|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToUpdate = $request->only(['name', 'email', 'position', 'department', 'leave_balance', 'manager_id']);
        
        if ($request->has('password')) {
            $dataToUpdate['password'] = Hash::make($request->password);
        }

        $employee = $this->service->updateEmployee($id, $dataToUpdate);

        return response()->json([
            'message' => 'Employee updated successfully',
            'data' => $employee
        ]);
    }

    /**
     * Delete employee
     */
    public function destroy($id)
    {
        $deleted = $this->service->deleteEmployee($id);

        if (!$deleted) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Employee deleted successfully'
        ]);
    }

    /**
     * Get employee hierarchy (subordinates)
     */
    public function subordinates($id)
    {
        $employee = $this->service->getEmployeeById($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $subordinates = $this->service->getSubordinates($id);

        return response()->json([
            'manager' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'position' => $employee->position,
            ],
            'subordinates' => $subordinates,
            'count' => $subordinates->count()
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics()
    {
        $stats = $this->service->getStatistics();

        return response()->json($stats);
    }

    /**
     * Get employee hierarchy (employee + manager + subordinates)
     */
    public function hierarchy($id)
    {
        $employee = $this->service->getEmployeeHierarchy($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => $employee,
                'manager' => $employee->manager,
                'subordinates' => $employee->subordinates
            ]
        ]);
    }

    /**
     * Update employee leave balance
     */
    public function updateLeaveBalance(Request $request, $id)
    {
        $employee = $this->service->getEmployeeById($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'leave_balance' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee = $this->service->updateLeaveBalance($id, $request->leave_balance);

        return response()->json([
            'message' => 'Leave balance updated successfully',
            'data' => $employee
        ]);
    }
}

