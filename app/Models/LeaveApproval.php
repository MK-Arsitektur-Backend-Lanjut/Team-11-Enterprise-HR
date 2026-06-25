<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "LeaveApproval",
    title: "Leave Approval Model",
    description: "Approval record for a leave request",
    required: ["id", "leave_request_id", "approver_id", "status", "approval_level"],
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "leave_request_id", type: "integer", example: 1),
        new OA\Property(property: "approver_id", type: "integer", example: 2),
        new OA\Property(property: "status", type: "string", example: "approved"),
        new OA\Property(property: "notes", type: "string", example: "Have a nice trip", nullable: true),
        new OA\Property(property: "approval_level", type: "integer", example: 1),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
class LeaveApproval extends Model
{
    protected $fillable = [
        'leave_request_id',
        'approver_id',
        'status',
        'notes',
        'approval_level',
    ];

    /**
     * Get the leave request that this approval belongs to.
     */
    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * Get the employee who is the approver.
     */
    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
}
