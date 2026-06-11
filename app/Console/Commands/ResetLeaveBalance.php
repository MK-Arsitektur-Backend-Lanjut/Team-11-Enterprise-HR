<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetLeaveBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:reset {--amount=12 : Default amount of leave balance}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset leave_balance for all employees to a specified amount (default 12)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $amount = (int) $this->option('amount');

        $this->info("Starting leave balance reset to {$amount} for all employees...");

        $startTime = microtime(true);
        
        // Use raw query for bulk updating thousands of rows efficiently
        $updatedRows = DB::table('employees')->update(['leave_balance' => $amount]);
        
        $endTime = microtime(true);
        $duration = number_format(($endTime - $startTime) * 1000, 2);

        $this->info("Successfully reset leave_balance for {$updatedRows} employees.");
        $this->info("Operation completed in {$duration} ms.");
    }
}
