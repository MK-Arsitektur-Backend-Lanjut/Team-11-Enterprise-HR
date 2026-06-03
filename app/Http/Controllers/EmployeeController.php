<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    /**
     * Get all employees with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $department = $request->input('department');
        $position = $request->input('position');
        $search = $request->input('search');

        $query = Employee::query()->with('manager:id,name,position');

        // Filter by department
        if ($department) {
            $query->where('department', $department);
        }

        // Filter by position
        if ($position) {
            $query->where('position', $position);
        }

        // Search by name or email
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $employees = $query->latest()->paginate($perPage);

        return response()->json($employees);
    }

    /**
     * Get single employee by ID
     */
    public function show($id)
    {
        $employee = Employee::with(['manager:id,name,position,department', 'subordinates:id,name,position,department'])
            ->find($id);

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

        $employee = Employee::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'position' => $request->position,
            'department' => $request->department,
            'leave_balance' => $request->leave_balance ?? 15,
            'manager_id' => $request->manager_id,
        ]);

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
        $employee = Employee::find($id);

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

        $employee->update($dataToUpdate);

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
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $employee->delete();

        return response()->json([
            'message' => 'Employee deleted successfully'
        ]);
    }

    /**
     * Get employee hierarchy (subordinates)
     */
    public function subordinates($id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $subordinates = Employee::where('manager_id', $id)
            ->select('id', 'name', 'email', 'position', 'department')
            ->get();

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
        $stats = [
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

        return response()->json($stats);
    }

    /**
     * Get employee hierarchy (employee + manager + subordinates)
     */
    public function hierarchy($id)
    {
        $employee = Employee::with(['manager', 'subordinates'])->find($id);

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
        $employee = Employee::find($id);

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

        $employee->leave_balance = $request->leave_balance;
        $employee->save();

        return response()->json([
            'message' => 'Leave balance updated successfully',
            'data' => $employee
        ]);
    }
}
