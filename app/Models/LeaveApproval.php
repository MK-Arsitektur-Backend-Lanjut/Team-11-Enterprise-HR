<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
