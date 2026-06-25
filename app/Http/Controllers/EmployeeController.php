<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[OA\Tag(name: "Employee", description: "API Endpoints for Employee Management")]
class EmployeeController extends Controller
{
    private $service;

    public function __construct(EmployeeService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: "/api/employees",
        summary: "Get all employees with pagination",
        security: [["bearerAuth" => []]],
        tags: ["Employee"]
    )]
    #[OA\Parameter(name: "per_page", description: "Number of items per page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 15))]
    #[OA\Parameter(name: "search", description: "Search by name or email", in: "query", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "department", description: "Filter by department", in: "query", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "position", description: "Filter by position", in: "query", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "current_page", type: "integer"),
                new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Employee")),
                new OA\Property(property: "total", type: "integer")
            ]
        )
    )]
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $filters = $request->only(['department', 'position', 'search']);
        
        $employees = $this->service->getPaginatedEmployees($perPage, $filters);

        return response()->json($employees);
    }

    #[OA\Get(
        path: "/api/employees/{id}",
        summary: "Get single employee by ID",
        security: [["bearerAuth" => []]],
        tags: ["Employee"]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(ref: "#/components/schemas/Employee")
    )]
    #[OA\Response(response: 404, description: "Employee not found")]
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

    #[OA\Post(
        path: "/api/employees",
        summary: "Create new employee",
        security: [["bearerAuth" => []]],
        tags: ["Employee"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name", "email", "password", "position", "department"],
            properties: [
                new OA\Property(property: "name", type: "string", example: "John Doe"),
                new OA\Property(property: "email", type: "string", format: "email", example: "john@enterprise.com"),
                new OA\Property(property: "password", type: "string", format: "password", example: "secret"),
                new OA\Property(property: "position", type: "string", example: "Software Engineer"),
                new OA\Property(property: "department", type: "string", example: "IT"),
                new OA\Property(property: "manager_id", type: "integer", nullable: true, example: null)
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Employee created successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "data", ref: "#/components/schemas/Employee")
            ]
        )
    )]
    #[OA\Response(response: 422, description: "Validation failed")]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email',
            'password' => 'required|string|min:6',
            'position' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'manager_id' => 'nullable|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'email', 'position', 'department', 'manager_id']);
        $data['password'] = Hash::make($request->password);
        $data['leave_balance'] = 12;

        $employee = $this->service->createEmployee($data);

        return response()->json([
            'message' => 'Employee created successfully',
            'data' => $employee
        ], 201);
    }

    #[OA\Put(
        path: "/api/employees/{id}",
        summary: "Update employee profile",
        security: [["bearerAuth" => []]],
        tags: ["Employee"]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "name", type: "string", example: "John Doe"),
                new OA\Property(property: "email", type: "string", format: "email", example: "john.new@enterprise.com"),
                new OA\Property(property: "password", type: "string", format: "password", example: "newsecret"),
                new OA\Property(property: "position", type: "string", example: "Senior Software Engineer"),
                new OA\Property(property: "department", type: "string", example: "IT")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Employee updated successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "data", ref: "#/components/schemas/Employee")
            ]
        )
    )]
    #[OA\Response(response: 404, description: "Employee not found")]
    #[OA\Response(response: 422, description: "Validation failed")]
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToUpdate = $request->only(['name', 'email', 'position', 'department']);
        
        if ($request->has('password')) {
            $dataToUpdate['password'] = Hash::make($request->password);
        }

        $employee = $this->service->updateEmployee($id, $dataToUpdate);

        return response()->json([
            'message' => 'Employee updated successfully',
            'data' => $employee
        ]);
    }

    #[OA\Delete(
        path: "/api/employees/{id}",
        summary: "Delete employee",
        security: [["bearerAuth" => []]],
        tags: ["Employee"]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "Employee deleted successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 404, description: "Employee not found")]
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

    #[OA\Get(
        path: "/api/employees/{id}/subordinates",
        summary: "Get employee's direct subordinates",
        security: [["bearerAuth" => []]],
        tags: ["Employee"]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "manager", type: "object", properties: [
                    new OA\Property(property: "id", type: "integer"),
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "position", type: "string")
                ]),
                new OA\Property(property: "subordinates", type: "array", items: new OA\Items(ref: "#/components/schemas/Employee")),
                new OA\Property(property: "count", type: "integer")
            ]
        )
    )]
    #[OA\Response(response: 404, description: "Employee not found")]
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

    #[OA\Get(
        path: "/api/employees/statistics",
        summary: "Get employee statistics by department",
        security: [["bearerAuth" => []]],
        tags: ["Employee"]
    )]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(
            type: "array",
            items: new OA\Items(
                type: "object",
                properties: [
                    new OA\Property(property: "department", type: "string"),
                    new OA\Property(property: "total", type: "integer")
                ]
            )
        )
    )]
    public function statistics()
    {
        $stats = $this->service->getStatistics();

        return response()->json($stats);
    }

    #[OA\Get(
        path: "/api/employees/{id}/hierarchy",
        summary: "Get complete employee hierarchy (manager & subordinates)",
        security: [["bearerAuth" => []]],
        tags: ["Employee"]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean"),
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "employee", ref: "#/components/schemas/Employee"),
                    new OA\Property(property: "manager", ref: "#/components/schemas/Employee"),
                    new OA\Property(property: "subordinates", type: "array", items: new OA\Items(ref: "#/components/schemas/Employee"))
                ])
            ]
        )
    )]
    #[OA\Response(response: 404, description: "Employee not found")]
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

    #[OA\Put(
        path: "/api/employees/{id}/leave-balance",
        summary: "Update employee leave balance manually (HR/Admin)",
        security: [["bearerAuth" => []]],
        tags: ["Employee"]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["leave_balance"],
            properties: [
                new OA\Property(property: "leave_balance", type: "integer", example: 10)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Leave balance updated successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "data", ref: "#/components/schemas/Employee")
            ]
        )
    )]
    #[OA\Response(response: 404, description: "Employee not found")]
    #[OA\Response(response: 422, description: "Validation failed")]
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
