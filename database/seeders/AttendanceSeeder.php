<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates attendance data for the last 30 days for a sample of employees.
     */
    public function run(): void
    {
        $this->command->info('Seeding attendance data...');

        // Seed attendance for first 100 employees to keep it manageable
        $employees = Employee::limit(100)->get();
        $now = Carbon::now();
        $startDate = $now->copy()->subDays(30);

        $attendanceData = [];
        $batchSize = 1000;

        foreach ($employees as $employee) {
            $currentDate = $startDate->copy();

            while ($currentDate->lte($now)) {
                // Skip weekends
                if ($currentDate->isWeekend()) {
                    $currentDate->addDay();
                    continue;
                }

                // 85% chance of attendance, 10% late, 5% absent
                $rand = rand(1, 100);

                if ($rand <= 85) {
                    // Present — clock in between 07:30 and 08:00
                    $clockInHour = 7;
                    $clockInMinute = rand(30, 59);
                    $status = 'present';
                    $lateMinutes = 0;
                } elseif ($rand <= 95) {
                    // Late — clock in between 08:01 and 09:30
                    $clockInHour = 8;
                    $clockInMinute = rand(1, 90);
                    if ($clockInMinute > 59) {
                        $clockInHour = 9;
                        $clockInMinute = $clockInMinute - 60;
                    }
                    $status = 'late';
                    $clockInTime = Carbon::createFromTime($clockInHour, $clockInMinute);
                    $workStart = Carbon::createFromTime(8, 0);
                    $lateMinutes = abs($clockInTime->diffInMinutes($workStart));
                } else {
                    // Absent
                    $attendanceData[] = [
                        'employee_id' => $employee->id,
                        'date' => $currentDate->toDateString(),
                        'clock_in' => null,
                        'clock_out' => null,
                        'status' => 'absent',
                        'late_minutes' => 0,
                        'work_hours' => 0,
                        'notes' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $currentDate->addDay();

                    if (count($attendanceData) >= $batchSize) {
                        DB::table('attendances')->insert($attendanceData);
                        $attendanceData = [];
                    }

                    continue;
                }

                // Clock out between 17:00 and 18:00
                $clockOutHour = rand(17, 18);
                $clockOutMinute = $clockOutHour === 18 ? rand(0, 30) : rand(0, 59);

                $clockIn = sprintf('%02d:%02d:00', $clockInHour, $clockInMinute);
                $clockOut = sprintf('%02d:%02d:00', $clockOutHour, $clockOutMinute);

                // Calculate work hours
                $workHours = round(
                    abs(Carbon::createFromFormat('H:i:s', $clockOut)->diffInMinutes(
                        Carbon::createFromFormat('H:i:s', $clockIn)
                    )) / 60,
                    2
                );

                $attendanceData[] = [
                    'employee_id' => $employee->id,
                    'date' => $currentDate->toDateString(),
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'work_hours' => $workHours,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($attendanceData) >= $batchSize) {
                    DB::table('attendances')->insert($attendanceData);
                    $this->command->info('  Inserted ' . $batchSize . ' attendance records...');
                    $attendanceData = [];
                }

                $currentDate->addDay();
            }
        }

        // Insert remaining
        if (!empty($attendanceData)) {
            DB::table('attendances')->insert($attendanceData);
        }

        $totalRecords = DB::table('attendances')->count();
        $this->command->info("✅ Total attendance records created: {$totalRecords}");
    }
}
