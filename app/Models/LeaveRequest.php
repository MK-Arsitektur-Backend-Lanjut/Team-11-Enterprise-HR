<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "LeaveRequest",
    title: "Leave Request Model",
    description: "Employee leave request",
    required: ["id", "employee_id", "start_date", "end_date", "reason", "type", "status"],
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "employee_id", type: "integer", example: 1),
        new OA\Property(property: "start_date", type: "string", format: "date", example: "2024-01-10"),
        new OA\Property(property: "end_date", type: "string", format: "date", example: "2024-01-12"),
        new OA\Property(property: "reason", type: "string", example: "Vacation"),
        new OA\Property(property: "type", type: "string", example: "annual"),
        new OA\Property(property: "status", type: "string", example: "pending"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'reason',
        'type',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the employee who submitted this leave request.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get all approval records for this leave request.
     */
    public function approvals()
    {
        return $this->hasMany(LeaveApproval::class);
    }
}
