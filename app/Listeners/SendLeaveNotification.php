<?php

namespace App\Listeners;

use App\Events\LeaveRequestStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendLeaveNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LeaveRequestStatusUpdated $event): void
    {
        // This is where you would send an actual email, SMS, or save to a database notifications table.
        // For demonstration, we simply log the notification.
        $leaveRequest = $event->leaveRequest;
        Log::info("Notification sent to Employee ID {$leaveRequest->employee_id}: Your leave request is now {$leaveRequest->status}.");
    }
}
