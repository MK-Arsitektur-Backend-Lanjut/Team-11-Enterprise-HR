<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes - All modules unified under auth:api middleware
Route::middleware('auth:api')->group(function () {
    // ==================== AUTH ENDPOINTS ====================
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // ==================== EMPLOYEE ENDPOINTS ====================
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index']);
        Route::get('/statistics', [EmployeeController::class, 'statistics']);
        Route::post('/', [EmployeeController::class, 'store']);
        
        Route::get('/{id}', [EmployeeController::class, 'show']);
        Route::put('/{id}', [EmployeeController::class, 'update']);
        Route::delete('/{id}', [EmployeeController::class, 'destroy']);
        Route::get('/{id}/subordinates', [EmployeeController::class, 'subordinates']);
        Route::get('/{id}/hierarchy', [EmployeeController::class, 'hierarchy']);
        Route::put('/{id}/leave-balance', [EmployeeController::class, 'updateLeaveBalance']);
    });

    // ==================== LEAVE REQUEST ENDPOINTS ====================
    Route::prefix('leaves')->group(function () {
        Route::post('/', [LeaveController::class, 'store']);
        Route::get('/my-requests', [LeaveController::class, 'myRequests']);
        Route::get('/subordinates', [LeaveController::class, 'subordinateRequests']);
    });

    // ==================== APPROVAL ENDPOINTS ====================
    Route::prefix('approvals')->group(function () {
        Route::get('/pending', [ApprovalController::class, 'pending']);
        Route::post('/level-1/{leave_request_id}', [ApprovalController::class, 'approveLevel1']);
        Route::post('/level-2/{leave_request_id}', [ApprovalController::class, 'approveLevel2']);
    });
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

