<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1/employees')->group(function () {
    Route::get('profile', [EmployeeController::class, 'profile']);
    Route::get('{id}/hierarchy', [EmployeeController::class, 'hierarchy']);
    // This route should ideally be protected so only authorized services can update it
    Route::put('{id}/leave-balance', [EmployeeController::class, 'updateLeaveBalance']);
});
