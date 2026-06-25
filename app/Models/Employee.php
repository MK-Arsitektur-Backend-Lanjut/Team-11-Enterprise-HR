<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Employee",
    title: "Employee Model",
    description: "Employee representation",
    required: ["id", "name", "email", "position", "department", "leave_balance"],
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "John Doe"),
        new OA\Property(property: "email", type: "string", format: "email", example: "john@enterprise.com"),
        new OA\Property(property: "position", type: "string", example: "Software Engineer"),
        new OA\Property(property: "department", type: "string", example: "IT"),
        new OA\Property(property: "leave_balance", type: "integer", example: 12),
        new OA\Property(property: "manager_id", type: "integer", nullable: true, example: null),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2024-01-01T00:00:00.000000Z"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2024-01-01T00:00:00.000000Z")
    ]
)]
class Employee extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'position',
        'department',
        'leave_balance',
        'manager_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get the manager of the employee.
     */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Get the subordinates of the employee.
     */
    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /**
     * Get all leave requests submitted by this employee.
     */
    public function leaveRequests()
    {
        return $this->hasMany(\App\Models\LeaveRequest::class, 'employee_id');
    }

    /**
     * Get all leave approvals where this employee is the approver.
     */
    public function leaveApprovals()
    {
        return $this->hasMany(\App\Models\LeaveApproval::class, 'approver_id');
    }
}
