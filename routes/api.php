<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\ApprovalController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1/leaves')->group(function () {
    Route::post('{employee_id}', [LeaveController::class, 'store']);
    Route::get('my-requests/{employee_id}', [LeaveController::class, 'myRequests']);
    Route::get('all', [LeaveController::class, 'allRequests']);
});

Route::prefix('v1/approvals')->group(function () {
    Route::get('pending/{approver_id}', [ApprovalController::class, 'pending']);
    Route::post('level-1/{leave_request_id}/{approver_id}', [ApprovalController::class, 'approveLevel1']);
    Route::post('level-2/{leave_request_id}/{approver_id}', [ApprovalController::class, 'approveLevel2']);
});
