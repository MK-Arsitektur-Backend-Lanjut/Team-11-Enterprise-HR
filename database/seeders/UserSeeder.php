<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Repositories\AuthRepository;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(AuthRepository $authRepo): void
    {
        // 1. Data Dummy Inti (Petinggi & Staff Khusus)
        $coreUsers = [
            [
                'name'        => 'Admin CEO',
                'email'       => 'ceo@enterprise.com',
                'password'    => 'password123',
                'employee_id' => 1,
            ],
            [
                'name'        => 'Manager User',
                'email'       => 'manager@enterprise.com',
                'password'    => 'password123',
                'employee_id' => 2,
            ],
            [
                'name'        => 'Staff Satu',
                'email'       => 'staff1@enterprise.com',
                'password'    => 'password123',
                'employee_id' => 3,
            ],
            [
                'name'        => 'Staff Dua',
                'email'       => 'staff2@enterprise.com',
                'password'    => 'password123',
                'employee_id' => 4,
            ],
            [
                'name'        => 'Staff Tiga',
                'email'       => 'staff3@enterprise.com',
                'password'    => 'password123',
                'employee_id' => 5,
            ],
            [
                'name'        => 'New Registrant',
                'email'       => 'new.user@enterprise.com',
                'password'    => 'rahasia123',
                'employee_id' => null, // Simulasi user baru daftar sebelum di-assign employee_id
            ]
        ];

        foreach ($coreUsers as $userData) {
            // Kita memanfaatkan method register dari AuthRepository agar alurnya sama persis dengan Controller
            $authRepo->register($userData);
        }

        // 2. Loop 5000 Data Dummy Tambahan
        $faker = \Faker\Factory::create('id_ID');
        
        // Kita pre-hash password sekali saja di luar loop untuk mempercepat proses.
        // Jika Hash::make() ditaruh di dalam loop 5000 kali, proses seeder bisa memakan waktu bermenit-menit.
        $hashedPassword = \Illuminate\Support\Facades\Hash::make('password123');
        $now = now();

        $bulkUsers = [];
        $chunkSize = 1000;

        for ($i = 1; $i <= 5000; $i++) {
            $bulkUsers[] = [
                'name'        => $faker->name,
                'email'       => "user{$i}_" . $faker->unique()->safeEmail,
                'password'    => $hashedPassword,
                'employee_id' => $i + 5, // Mulai dari ID 6 (melanjutkan ID core users 1-5) agar unique
                'created_at'  => $now,
                'updated_at'  => $now,
            ];

            // Insert data per kelompok (chunk) agar tidak memberatkan memori RAM
            if ($i % $chunkSize === 0) {
                \App\Models\User::insert($bulkUsers);
                $bulkUsers = []; // Reset array setelah insert
            }
        }

        // Jika masih ada sisa di array yang belum masuk chunk
        if (!empty($bulkUsers)) {
            \App\Models\User::insert($bulkUsers);
        }
    }
}
