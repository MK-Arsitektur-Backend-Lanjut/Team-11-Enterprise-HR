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

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
