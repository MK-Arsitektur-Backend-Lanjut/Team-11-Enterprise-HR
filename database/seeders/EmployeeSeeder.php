<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1 CEO
        $ceo = Employee::factory()->create([
            'position' => 'CEO',
            'department' => 'Executive',
            'manager_id' => null,
        ]);

        // 10 Directors
        $directors = Employee::factory()->count(10)->create([
            'position' => 'Director',
            'department' => 'Executive',
            'manager_id' => $ceo->id,
        ]);

        // 50 Managers
        $managers = Employee::factory()->count(50)->make([
            'position' => 'Manager',
        ])->each(function ($manager) use ($directors) {
            $manager->manager_id = $directors->random()->id;
            $manager->department = fake()->randomElement(['HR', 'IT', 'Finance', 'Marketing', 'Sales', 'Operations']);
            $manager->save();
        });

        // ~4939 Staff
        $staffCount = 4939; // Total 5000: 1 CEO + 10 Dir + 50 Mgr + 4939 Staff

        $staffsData = [];
        $now = now();
        $managerIds = $managers->pluck('id')->toArray();
        $managerDepartments = $managers->pluck('department', 'id')->toArray();

        for ($i = 0; $i < $staffCount; $i++) {
            $managerId = $managerIds[array_rand($managerIds)];
            $staffsData[] = [
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'position' => 'Staff',
                'department' => $managerDepartments[$managerId],
                'leave_balance' => rand(0, 20),
                'manager_id' => $managerId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($staffsData, 1000) as $chunk) {
            Employee::insert($chunk);
        }
    }
}
