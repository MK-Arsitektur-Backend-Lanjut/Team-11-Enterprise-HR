<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Membuat user demo yang terhubung ke employee_id di Modul Employee.
     * Struktur employees di Modul Employee:
     *   - ID 1: CEO (tidak punya manager)
     *   - ID 2: Director/Manager (manager_id = 1)
     *   - ID 3-5: Staff (manager_id = 2)
     */
    public function run(): void
    {
        // CEO
        User::create([
            'name'        => 'Admin CEO',
            'email'       => 'ceo@enterprise.com',
            'password'    => Hash::make('password'),
            'employee_id' => 1,
        ]);

        // Director / Manager
        User::create([
            'name'        => 'Manager User',
            'email'       => 'manager@enterprise.com',
            'password'    => Hash::make('password'),
            'employee_id' => 2,
        ]);

        // Staff 1
        User::create([
            'name'        => 'Staff Satu',
            'email'       => 'staff1@enterprise.com',
            'password'    => Hash::make('password'),
            'employee_id' => 3,
        ]);

        // Staff 2
        User::create([
            'name'        => 'Staff Dua',
            'email'       => 'staff2@enterprise.com',
            'password'    => Hash::make('password'),
            'employee_id' => 4,
        ]);

        // Staff 3
        User::create([
            'name'        => 'Staff Tiga',
            'email'       => 'staff3@enterprise.com',
            'password'    => Hash::make('password'),
            'employee_id' => 5,
        ]);
    }
}
