<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Authentication", description: "API Endpoints for Authentication")]
class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    #[OA\Post(
        path: "/api/register",
        summary: "Register a new employee",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name", "email", "password", "password_confirmation", "position", "department"],
            properties: [
                new OA\Property(property: "name", type: "string", example: "Jane Doe"),
                new OA\Property(property: "email", type: "string", format: "email", example: "jane@enterprise.com"),
                new OA\Property(property: "password", type: "string", format: "password", example: "secret"),
                new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "secret"),
                new OA\Property(property: "position", type: "string", example: "HR Manager"),
                new OA\Property(property: "department", type: "string", example: "HR"),
                new OA\Property(property: "leave_balance", type: "integer", example: 12),
                new OA\Property(property: "manager_id", type: "integer", nullable: true, example: null)
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Employee registered successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "employee", ref: "#/components/schemas/Employee"),
                new OA\Property(property: "token", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Validation failed")]
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:employees',
            'password' => 'required|string|min:6|confirmed',
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'leave_balance' => 'integer|min:0',
            'manager_id' => 'nullable|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        try {
            $employee = $this->authService->registerEmployee($request->all());
            $token = auth('api')->login($employee);

            return response()->json([
                'message' => 'Employee registered successfully',
                'employee' => $employee,
                'token' => $token,
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration failed. Please try again.'], 500);
        }
    }

    #[OA\Post(
        path: "/api/login",
        summary: "Login to the application",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "password"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email", example: "jane@enterprise.com"),
                new OA\Property(property: "password", type: "string", format: "password", example: "secret")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Successful login",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "access_token", type: "string"),
                new OA\Property(property: "token_type", type: "string", example: "bearer"),
                new OA\Property(property: "expires_in", type: "integer")
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (! $token = $this->authService->login($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    #[OA\Get(
        path: "/api/me",
        summary: "Get current authenticated user info",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(ref: "#/components/schemas/Employee")
    )]
    #[OA\Response(response: 401, description: "Unauthenticated")]
    public function me()
    {
        return response()->json(auth('api')->user()->load('manager', 'subordinates'));
    }

    #[OA\Post(
        path: "/api/logout",
        summary: "Logout the user",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(
        response: 200,
        description: "Successfully logged out",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Unauthenticated")]
    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    #[OA\Post(
        path: "/api/refresh",
        summary: "Refresh JWT token",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(
        response: 200,
        description: "Token refreshed successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "access_token", type: "string"),
                new OA\Property(property: "token_type", type: "string", example: "bearer"),
                new OA\Property(property: "expires_in", type: "integer")
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Unauthenticated")]
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }
}
