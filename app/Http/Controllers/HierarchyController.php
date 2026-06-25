<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Services\HierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

#[OA\Tag(name: "Hierarchy", description: "API Endpoints for Organization Hierarchy Management (HR & CEO Only)")]
class HierarchyController extends Controller
{
    private $service;

    public function __construct(HierarchyService $service)
    {
        $this->service = $service;
    }

    #[OA\Put(
        path: "/api/employees/{id}/manager",
        summary: "Assign or change a manager for an employee",
        security: [["bearerAuth" => []]],
        tags: ["Hierarchy"]
    )]
    #[OA\Parameter(name: "id", description: "Employee ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["manager_id"],
            properties: [
                new OA\Property(property: "manager_id", type: "integer", example: 1)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Manager assigned successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "data", ref: "#/components/schemas/Employee")
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Failed to assign manager (e.g. Circular Reference detected)")]
    #[OA\Response(response: 422, description: "Validation failed")]
    public function assignManager(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'manager_id' => 'required|exists:employees,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = $this->service->assignManager($id, $request->manager_id);
            return response()->json([
                'message' => 'Manager assigned successfully',
                'data' => $employee
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to assign manager',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[OA\Delete(
        path: "/api/employees/{id}/manager",
        summary: "Remove the manager from an employee",
        security: [["bearerAuth" => []]],
        tags: ["Hierarchy"]
    )]
    #[OA\Parameter(name: "id", description: "Employee ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "Manager removed successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Failed to remove manager")]
    public function removeManager($id)
    {
        try {
            $this->service->removeManager($id);
            return response()->json([
                'message' => 'Manager removed successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to remove manager',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[OA\Post(
        path: "/api/employees/{manager_id}/subordinates",
        summary: "Bulk assign subordinates to a manager",
        security: [["bearerAuth" => []]],
        tags: ["Hierarchy"]
    )]
    #[OA\Parameter(name: "manager_id", description: "Manager ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["subordinate_ids"],
            properties: [
                new OA\Property(
                    property: "subordinate_ids",
                    type: "array",
                    items: new OA\Items(type: "integer"),
                    example: [3, 4, 5]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Subordinates assigned successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Failed to assign subordinates")]
    #[OA\Response(response: 422, description: "Validation failed")]
    public function addSubordinates(Request $request, $managerId)
    {
        $validator = Validator::make($request->all(), [
            'subordinate_ids' => 'required|array',
            'subordinate_ids.*' => 'exists:employees,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->service->addSubordinates($managerId, $request->subordinate_ids);
            return response()->json([
                'message' => 'Subordinates assigned successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to assign subordinates',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[OA\Delete(
        path: "/api/employees/{manager_id}/subordinates/{subordinate_id}",
        summary: "Remove a specific subordinate from a manager's team",
        security: [["bearerAuth" => []]],
        tags: ["Hierarchy"]
    )]
    #[OA\Parameter(name: "manager_id", description: "Manager ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "subordinate_id", description: "Subordinate ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(
        response: 200,
        description: "Subordinate removed successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 400, description: "Failed to remove subordinate")]
    public function removeSubordinate($managerId, $subordinateId)
    {
        try {
            // Re-use removeManager to set manager_id to null
            $this->service->removeManager($subordinateId);
            return response()->json([
                'message' => 'Subordinate removed successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to remove subordinate',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
