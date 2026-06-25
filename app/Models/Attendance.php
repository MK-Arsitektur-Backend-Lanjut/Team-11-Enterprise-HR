<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Attendance",
    title: "Attendance Model",
    description: "Employee attendance record",
    required: ["id", "employee_id", "date", "status"],
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "employee_id", type: "integer", example: 1),
        new OA\Property(property: "date", type: "string", format: "date", example: "2024-01-01"),
        new OA\Property(property: "clock_in", type: "string", format: "time", example: "08:00:00", nullable: true),
        new OA\Property(property: "clock_out", type: "string", format: "time", example: "17:00:00", nullable: true),
        new OA\Property(property: "status", type: "string", example: "present"),
        new OA\Property(property: "late_minutes", type: "integer", example: 0),
        new OA\Property(property: "work_hours", type: "number", format: "float", example: 8.5),
        new OA\Property(property: "notes", type: "string", example: "On time", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'late_minutes',
        'work_hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'late_minutes' => 'integer',
            'work_hours' => 'decimal:2',
        ];
    }

    /**
     * Get the employee that owns the attendance record.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
