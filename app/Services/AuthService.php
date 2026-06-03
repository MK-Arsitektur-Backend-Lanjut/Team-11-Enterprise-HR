<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthService
{
    /**
     * Handle the registration of a new employee.
     * Uses database transaction to prevent partial inserts 
     * and handle potential concurrency issues.
     *
     * @param array $data
     * @return Employee
     */
    public function registerEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $employee = Employee::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'position' => $data['position'],
                'department' => $data['department'],
                'leave_balance' => $data['leave_balance'] ?? 0,
                'manager_id' => $data['manager_id'] ?? null,
            ]);

            return $employee;
        });
    }

    /**
     * Handle the login process.
     *
     * @param array $credentials
     * @return string|false JWT Token
     */
    public function login(array $credentials)
    {
        // auth('api')->attempt() otomatis melakukan pengecekan hashing bcrypt
        return auth('api')->attempt($credentials);
    }
}
