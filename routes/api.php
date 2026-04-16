<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollExportController;

/*
|--------------------------------------------------------------------------
| Auth Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Requires Sanctum Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth (authenticated)
    Route::prefix('v1/auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Employee
    Route::prefix('v1/employees')->group(function () {
        Route::get('profile', [EmployeeController::class, 'profile']);
        Route::get('{id}/hierarchy', [EmployeeController::class, 'hierarchy']);
        Route::put('{id}/leave-balance', [EmployeeController::class, 'updateLeaveBalance']);
    });

    // Attendance
    Route::prefix('v1/attendance')->group(function () {
        Route::post('check-in', [AttendanceController::class, 'checkIn']);
        Route::post('check-out', [AttendanceController::class, 'checkOut']);
        Route::get('today', [AttendanceController::class, 'today']);
        Route::get('history', [AttendanceController::class, 'history']);
        Route::get('summary/monthly', [AttendanceController::class, 'monthlySummary']);
        Route::get('stats/department', [AttendanceController::class, 'departmentStats']);
    });

    // Leave Management
    Route::prefix('v1/leaves')->group(function () {
        Route::post('/', [LeaveController::class, 'apply']);
        Route::get('/', [LeaveController::class, 'history']);
        Route::get('balance', [LeaveController::class, 'balance']);
        Route::put('{id}/approve', [LeaveController::class, 'approve']);
        Route::put('{id}/reject', [LeaveController::class, 'reject']);
    });

    // Payroll Export (Admin only)
    Route::prefix('v1/payroll')->group(function () {
        Route::get('export/leave', [PayrollExportController::class, 'exportLeaveCsv']);
        Route::get('summary', [PayrollExportController::class, 'summary']);
    });
});
