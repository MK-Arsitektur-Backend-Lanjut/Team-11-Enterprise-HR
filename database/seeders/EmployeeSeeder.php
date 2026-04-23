<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Leave;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Generates ~5000 employees with linked user accounts,
     * attendance records (30 days), and leave data.
     * All user passwords are: 1234
     */
    public function run(): void
    {
        $password = Hash::make('1234');
        $now = now();

        // =========================================================
        // 1. ADMIN USER
        // =========================================================
        $adminUser = User::create([
            'name'     => 'Admin System',
            'email'    => 'admin1@attendance.test',
            'password' => $password,
        ]);

        $adminEmployee = Employee::factory()->create([
            'user_id'    => $adminUser->id,
            'name'       => 'Admin System',
            'email'      => 'admin1@attendance.test',
            'position'   => 'System Administrator',
            'department' => 'IT',
            'manager_id' => null,
            'leave_balance' => 12,
        ]);

        // =========================================================
        // 2. CEO
        // =========================================================
        $ceoUser = User::create([
            'name'     => 'CEO Company',
            'email'    => 'ceo2@attendance.test',
            'password' => $password,
        ]);

        $ceo = Employee::factory()->create([
            'user_id'    => $ceoUser->id,
            'name'       => 'CEO Company',
            'email'      => 'ceo2@attendance.test',
            'position'   => 'CEO',
            'department' => 'Executive',
            'manager_id' => null,
            'leave_balance' => 20,
        ]);

        $currentId = 3;

        // =========================================================
        // 3. DIRECTORS (10)
        // =========================================================
        $directors = collect();
        for ($i = 1; $i <= 10; $i++) {
            $name = fake()->name();
            $email = "director{$currentId}@attendance.test";

            $dirUser = User::create([
                'name'     => $name,
                'email'    => $email,
                'password' => $password,
            ]);

            $director = Employee::factory()->create([
                'user_id'    => $dirUser->id,
                'name'       => $name,
                'email'      => $email,
                'position'   => 'Director',
                'department' => 'Executive',
                'manager_id' => $ceo->id,
                'leave_balance' => 15,
            ]);

            $directors->push($director);
            $currentId++;
        }

        // =========================================================
        // 4. MANAGERS (50)
        // =========================================================
        $departments = ['HR', 'IT', 'Finance', 'Marketing', 'Sales', 'Operations'];
        $managers = collect();

        for ($i = 1; $i <= 50; $i++) {
            $name = fake()->name();
            $email = "manager{$currentId}@attendance.test";
            $dept = $departments[array_rand($departments)];

            $mgrUser = User::create([
                'name'     => $name,
                'email'    => $email,
                'password' => $password,
            ]);

            $manager = Employee::factory()->create([
                'user_id'    => $mgrUser->id,
                'name'       => $name,
                'email'      => $email,
                'position'   => 'Manager',
                'department' => $dept,
                'manager_id' => $directors->random()->id,
                'leave_balance' => rand(8, 15),
            ]);

            $managers->push($manager);
            $currentId++;
        }

        // =========================================================
        // 5. STAFF (~4938) — Bulk insert for performance
        // =========================================================
        $staffCount = 4938; // Total: 1 admin + 1 CEO + 10 Dir + 50 Mgr + 4938 Staff ≈ 5000
        $managerIds = $managers->pluck('id')->toArray();
        $managerDepartments = $managers->pluck('department', 'id')->toArray();

        // Bulk create users first
        $usersData = [];
        $staffEmails = [];
        for ($i = 0; $i < $staffCount; $i++) {
            $email = "staff{$currentId}@attendance.test";
            $staffEmails[] = $email;
            $usersData[] = [
                'name'       => fake()->name(),
                'email'      => $email,
                'password'   => $password,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $currentId++;
        }

        // Insert users in chunks of 500
        foreach (array_chunk($usersData, 500) as $chunk) {
            User::insert($chunk);
        }

        // Get IDs of newly created staff users
        $staffUsers = User::whereIn('email', $staffEmails)
            ->pluck('id', 'email')
            ->toArray();

        // Create employees linked to users
        $employeesData = [];
        foreach ($usersData as $user) {
            $email = $user['email'];
            $managerId = $managerIds[array_rand($managerIds)];
            $employeesData[] = [
                'user_id'       => $staffUsers[$email],
                'name'          => $user['name'],
                'email'         => $email,
                'position'      => 'Staff',
                'department'    => $managerDepartments[$managerId],
                'leave_balance' => rand(5, 20),
                'manager_id'    => $managerId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($employeesData, 500) as $chunk) {
            Employee::insert($chunk);
        }

        $this->command->info('✅ 5000 employees with user accounts created (password: 1234)');

        // =========================================================
        // 6. ATTENDANCE DATA (last 30 days, sample ~200 employees)
        // =========================================================
        $this->command->info('⏳ Generating attendance data...');

        $sampleEmployeeIds = Employee::inRandomOrder()->limit(200)->pluck('id')->toArray();
        $attendanceData = [];

        foreach ($sampleEmployeeIds as $empId) {
            for ($day = 29; $day >= 0; $day--) {
                $date = Carbon::today()->subDays($day);

                // Skip weekends
                if ($date->isWeekend()) {
                    continue;
                }

                // 90% chance of attendance
                if (rand(1, 100) > 90) {
                    continue;
                }

                // Random check-in between 07:30 and 09:30
                $checkInHour = rand(7, 9);
                $checkInMinute = rand(0, 59);
                $checkIn = $date->copy()->setTime($checkInHour, $checkInMinute);

                // Status based on check-in time
                $status = $checkInHour >= 9 && $checkInMinute > 0 ? 'late' : 'present';

                // Random check-out between 16:00 and 18:30
                $checkOutHour = rand(16, 18);
                $checkOutMinute = rand(0, 30);
                $checkOut = $date->copy()->setTime($checkOutHour, $checkOutMinute);

                // 5% chance of half_day (check out early)
                if (rand(1, 100) <= 5) {
                    $checkOut = $date->copy()->setTime(rand(11, 13), rand(0, 59));
                    $status = 'half_day';
                }

                $attendanceData[] = [
                    'employee_id' => $empId,
                    'date'        => $date->toDateString(),
                    'check_in'    => $checkIn,
                    'check_out'   => $checkOut,
                    'status'      => $status,
                    'notes'       => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        foreach (array_chunk($attendanceData, 500) as $chunk) {
            Attendance::insert($chunk);
        }

        $this->command->info('✅ ' . count($attendanceData) . ' attendance records created');

        // =========================================================
        // 7. LEAVE DATA (sample ~100 employees)
        // =========================================================
        $this->command->info('⏳ Generating leave data...');

        $leaveTypes = ['annual', 'sick', 'personal', 'unpaid'];
        $leaveStatuses = ['pending', 'approved', 'rejected'];
        $leaveEmployeeIds = Employee::inRandomOrder()->limit(100)->pluck('id')->toArray();
        $approverIds = $managers->pluck('id')->toArray();
        $leaveData = [];

        foreach ($leaveEmployeeIds as $empId) {
            // 1-3 leave requests per employee
            $leaveCount = rand(1, 3);

            for ($l = 0; $l < $leaveCount; $l++) {
                $startDate = Carbon::today()->subDays(rand(1, 60));
                $totalDays = rand(1, 5);
                $endDate = $startDate->copy()->addDays($totalDays - 1);
                $status = $leaveStatuses[array_rand($leaveStatuses)];
                $approverId = $approverIds[array_rand($approverIds)];

                $leaveData[] = [
                    'employee_id' => $empId,
                    'type'        => $leaveTypes[array_rand($leaveTypes)],
                    'start_date'  => $startDate->toDateString(),
                    'end_date'    => $endDate->toDateString(),
                    'total_days'  => $totalDays,
                    'reason'      => fake()->sentence(rand(5, 15)),
                    'status'      => $status,
                    'approved_by' => $status !== 'pending' ? $approverId : null,
                    'approved_at' => $status !== 'pending' ? $now : null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        foreach (array_chunk($leaveData, 500) as $chunk) {
            Leave::insert($chunk);
        }

        $this->command->info('✅ ' . count($leaveData) . ' leave records created');
        $this->command->info('');
        $this->command->info('🔐 Login credentials:');
        $this->command->info('   Admin:    admin1@attendance.test / 1234');
        $this->command->info('   CEO:      ceo2@attendance.test / 1234');
        $this->command->info('   Director: director3@attendance.test / 1234 (hingga director12)');
        $this->command->info('   Manager:  manager13@attendance.test / 1234 (hingga manager62)');
        $this->command->info('   Staff:    staff63@attendance.test / 1234 (dan seterusnya)');
    }
}
