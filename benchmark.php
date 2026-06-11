<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$repo = app(\App\Repositories\EmployeeRepository::class);

echo "--- BEFORE INDEXING ---\n";

// 1. Get Subordinates
$manager = \App\Models\Employee::where('position', 'Director')->first();
$startTime = microtime(true);
$subs = $repo->getSubordinates($manager->id);
$endTime = microtime(true);
echo "getSubordinates (manager_id) took: " . number_format(($endTime - $startTime) * 1000, 2) . " ms\n";

// 2. Get Statistics (Groups by department and position)
$startTime = microtime(true);
$stats = $repo->getStatistics();
$endTime = microtime(true);
echo "getStatistics (groupBy department & position) took: " . number_format(($endTime - $startTime) * 1000, 2) . " ms\n";

// 3. Search with LIKE
$filters = ['search' => 'Staff Member 4000'];
$startTime = microtime(true);
$res = $repo->getPaginatedEmployees(15, $filters);
$endTime = microtime(true);
echo "getPaginatedEmployees (search name/email) took: " . number_format(($endTime - $startTime) * 1000, 2) . " ms\n";
