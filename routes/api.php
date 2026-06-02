<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveController;
use Illuminate\Support\Facades\Route;

// Auth routes (existing)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

// V1 API routes — Attendance & Reporting module
Route::prefix('v1')->middleware('auth:api')->group(function () {

    // Attendance
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);
    Route::get('/attendance/me', [AttendanceController::class, 'myAttendance']);
    Route::get('/attendance/report', [AttendanceController::class, 'getReport']);
    Route::get('/attendance/report/export', [AttendanceController::class, 'exportReport']);

    // Leaves
    Route::get('/leaves/me', [LeaveController::class, 'myLeaves']);
    Route::get('/leaves/export/payroll', [LeaveController::class, 'exportForPayroll']);
    Route::post('/leaves/sync', [LeaveController::class, 'syncFromApproval']);
    Route::get('/leaves', [LeaveController::class, 'index']);
    Route::get('/leaves/{id}', [LeaveController::class, 'show']);
    Route::put('/leaves/employee/{id}/balance', [LeaveController::class, 'updateLeaveBalance']);
});

