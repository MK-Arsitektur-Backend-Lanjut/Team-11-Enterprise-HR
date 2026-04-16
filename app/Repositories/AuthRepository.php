<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthRepository
{
    /**
     * Register a new user and link to an employee profile.
     *
     * @param array $data ['name', 'email', 'password', 'position', 'department', 'manager_id']
     * @return array ['user' => User, 'employee' => Employee, 'token' => string]
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Create user account
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => $data['password'], // auto-hashed via cast
            ]);

            // Create linked employee profile
            $employee = Employee::create([
                'user_id'       => $user->id,
                'name'          => $data['name'],
                'email'         => $data['email'],
                'position'      => $data['position'] ?? 'Staff',
                'department'    => $data['department'] ?? 'General',
                'leave_balance' => $data['leave_balance'] ?? 12,
                'manager_id'    => $data['manager_id'] ?? null,
            ]);

            // Generate Sanctum token
            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user'     => $user->load('employee'),
                'employee' => $employee,
                'token'    => $token,
            ];
        });
    }

    /**
     * Authenticate user and return Sanctum token.
     *
     * @param array $credentials ['email', 'password']
     * @return array ['user' => User, 'token' => string]
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke previous tokens to prevent token accumulation
        $user->tokens()->delete();

        // Create new Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user'  => $user->load('employee'),
            'token' => $token,
        ];
    }

    /**
     * Logout user by revoking current token.
     *
     * @param User $user
     * @return bool
     */
    public function logout(User $user): bool
    {
        // Revoke the current access token
        $user->currentAccessToken()->delete();

        return true;
    }

    /**
     * Revoke all tokens and issue a new one (token refresh).
     *
     * @param User $user
     * @return array ['user' => User, 'token' => string]
     */
    public function refreshToken(User $user): array
    {
        // Delete all existing tokens
        $user->tokens()->delete();

        // Issue new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user'  => $user->load('employee'),
            'token' => $token,
        ];
    }

    /**
     * Get authenticated user with employee profile.
     *
     * @param User $user
     * @return User
     */
    public function getAuthenticatedUser(User $user): User
    {
        return $user->load('employee.manager');
    }
}
