<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password123');
        $now = now();

        // 1. Create CEO
        $ceo = Employee::create([
            'name' => 'John Doe (CEO)',
            'email' => 'ceo@enterprise.com',
            'password' => $password,
            'position' => 'CEO',
            'department' => 'Executive',
            'leave_balance' => 30,
            'manager_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Create 10 Directors
        $directors = [];
        $departments = ['HR', 'IT', 'Finance', 'Marketing', 'Sales', 'Operations', 'Legal', 'R&D', 'Engineering', 'Customer Support'];
        
        for ($i = 0; $i < 10; $i++) {
            $directors[] = Employee::create([
                'name' => 'Director ' . $departments[$i],
                'email' => 'director.' . strtolower(str_replace(' ', '', $departments[$i])) . '@enterprise.com',
                'password' => $password,
                'position' => 'Director',
                'department' => $departments[$i],
                'leave_balance' => 25,
                'manager_id' => $ceo->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Create 50 Managers (5 per Director/Department)
        $managers = [];
        foreach ($directors as $director) {
            for ($i = 1; $i <= 5; $i++) {
                $managers[] = Employee::create([
                    'name' => 'Manager ' . $i . ' - ' . $director->department,
                    'email' => 'manager' . $i . '.' . strtolower(str_replace(' ', '', $director->department)) . '@enterprise.com',
                    'password' => $password,
                    'position' => 'Manager',
                    'department' => $director->department,
                    'leave_balance' => 20,
                    'manager_id' => $director->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 4. Create 4939 Staff (~98-99 per Manager)
        // Kita gunakan insert() per batch (chunk) untuk performa yang sangat cepat
        // daripada memanggil Employee::create() satu per satu yang akan membuat 4939 query insert.
        
        $staffCount = 4939;
        $staffsData = [];
        $managerIds = array_column($managers, 'id');
        $managerDepts = array_column($managers, 'department', 'id');

        for ($i = 1; $i <= $staffCount; $i++) {
            $randomManagerId = $managerIds[array_rand($managerIds)];
            
            $staffsData[] = [
                'name' => 'Staff Member ' . $i,
                'email' => 'staff' . $i . '@enterprise.com',
                'password' => $password,
                'position' => 'Staff',
                'department' => $managerDepts[$randomManagerId],
                'leave_balance' => rand(10, 15),
                'manager_id' => $randomManagerId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert in chunks of 1000 to prevent memory exhaustion and speed up database insertion
        foreach (array_chunk($staffsData, 1000) as $chunk) {
            Employee::insert($chunk);
        }
    }
}
