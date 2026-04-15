<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function approvals()
    {
        return $this->hasMany(LeaveApproval::class);
    }
}
