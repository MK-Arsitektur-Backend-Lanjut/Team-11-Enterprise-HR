<?php

namespace App\Http\Controllers;

use App\Services\HierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class HierarchyController extends Controller
{
    private $service;

    public function __construct(HierarchyService $service)
    {
        $this->service = $service;
    }

    /**
     * PUT /api/employees/{id}/manager
     * Assign or change a manager for an employee.
     */
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

    /**
     * DELETE /api/employees/{id}/manager
     * Remove the manager from an employee.
     */
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

    /**
     * POST /api/employees/{manager_id}/subordinates
     * Bulk assign subordinates to a manager.
     */
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

    /**
     * DELETE /api/employees/{manager_id}/subordinates/{subordinate_id}
     * Remove a specific subordinate from a manager's team.
     */
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
