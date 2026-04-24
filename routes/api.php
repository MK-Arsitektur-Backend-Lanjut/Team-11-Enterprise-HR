<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PayrollExportController;

/*
|--------------------------------------------------------------------------
| Attendance & Reporting Microservice API Routes
|--------------------------------------------------------------------------
|
| Port: 8001 (default)
| Base URL: /api/v1
|
| Autentikasi: Sanctum (Bearer Token / JWT-style)
| Komunikasi antar-service: HTTP Request ke Modul Employee & Approval
|
*/

// ─────────────────────────────────────────────
// Public Routes (Tanpa Auth)
// ─────────────────────────────────────────────
Route::prefix('v1/auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// ─────────────────────────────────────────────
// Protected Routes (Harus Login / Bearer Token)
// ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth: Profile & Logout
    Route::prefix('v1/auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Attendance: Check-in, Check-out, History, Statistik Department
    Route::prefix('v1/attendance')->group(function () {
        Route::post('check-in', [AttendanceController::class, 'checkIn']);
        Route::post('check-out', [AttendanceController::class, 'checkOut']);
        Route::get('history', [AttendanceController::class, 'history']);
        Route::get('department-stats', [AttendanceController::class, 'departmentStats']);
    });

    // Payroll Export: JSON & CSV
    Route::prefix('v1/payroll')->group(function () {
        Route::get('export', [PayrollExportController::class, 'export']);
        Route::get('export-csv', [PayrollExportController::class, 'exportCsv']);
    });
});
