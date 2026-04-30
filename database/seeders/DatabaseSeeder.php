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
        // Memanggil UserSeeder untuk mengisi data user (sekaligus register)
        $this->call([
            UserSeeder::class,
        ]);
    }
}
