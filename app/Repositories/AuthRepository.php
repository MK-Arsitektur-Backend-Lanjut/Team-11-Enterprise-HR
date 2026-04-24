<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthRepository
{
    /**
     * Registrasi user baru.
     */
    public function register(array $data): User
    {
        return User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => Hash::make($data['password']),
            'employee_id' => $data['employee_id'] ?? null,
        ]);
    }

    /**
     * Cari user berdasarkan email.
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Cari user berdasarkan ID.
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Verifikasi password user.
     */
    public function verifyPassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }
}
