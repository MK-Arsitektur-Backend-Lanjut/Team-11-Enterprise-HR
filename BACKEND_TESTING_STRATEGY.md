# 🧪 Backend Testing Strategy - Enterprise HR System

## 📋 Table of Contents
1. [Mengapa Black Box Testing Tidak Cukup?](#mengapa-black-box-testing-tidak-cukup)
2. [Teknik Testing yang Relevan untuk Backend](#teknik-testing-yang-relevan)
3. [Testing Pyramid untuk Backend API](#testing-pyramid)
4. [Implementation Guide](#implementation-guide)
5. [Testing Tools & Framework](#testing-tools)
6. [Best Practices](#best-practices)

---

## ❌ Mengapa Black Box Testing Tidak Cukup?

### **Limitasi Black Box Testing untuk Backend API**

**Black Box Testing** fokus pada testing dari perspektif eksternal tanpa pengetahuan tentang internal logic:

```
┌────────────────────────────────┐
│   Black Box Testing View      │
│                                │
│   Input ──► [API] ──► Output  │
│                                │
│   ❌ Tidak melihat:            │
│   - Database queries           │
│   - Business logic             │
│   - Exception handling         │
│   - Performance bottleneck     │
│   - Security vulnerabilities   │
└────────────────────────────────┘
```

### **Masalah dengan Black Box Testing Only:**

❌ **Tidak bisa detect:**
- SQL Injection vulnerabilities
- N+1 query problems
- Memory leaks
- Race conditions
- Business logic errors
- Code coverage gaps

❌ **Tidak efisien:**
- Butuh setup environment lengkap
- Slow execution time
- Sulit isolate bugs
- Tidak bisa test edge cases

---

## ✅ Teknik Testing yang Relevan untuk Backend

### **1. Unit Testing (White Box)** 🎯

**Definisi:** Testing individual units/components (functions, methods, classes) secara terisolasi.

**Karakteristik:**
- ✅ Fast execution (milliseconds)
- ✅ Test internal logic
- ✅ High code coverage
- ✅ Easy to debug
- ✅ Mock dependencies

**Example Use Cases:**
```php
// Test business logic
- Calculation algorithms
- Data validation
- Transformation logic
- Helper functions
- Service layer methods
```

**Contoh Implementasi:**
```php
// tests/Unit/Services/LeaveServiceTest.php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\LeaveService;
use App\Models\Employee;
use App\Repositories\LeaveRequestRepository;
use Mockery;

class LeaveServiceTest extends TestCase
{
    protected $leaveService;
    protected $leaveRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock dependencies
        $this->leaveRepository = Mockery::mock(LeaveRequestRepository::class);
        $this->leaveService = new LeaveService($this->leaveRepository);
    }

    /** @test */
    public function it_calculates_total_leave_days_correctly()
    {
        // Arrange
        $startDate = '2024-01-01';
        $endDate = '2024-01-05';
        
        // Act
        $totalDays = $this->leaveService->calculateLeaveDays($startDate, $endDate);
        
        // Assert
        $this->assertEquals(5, $totalDays);
    }

    /** @test */
    public function it_throws_exception_when_end_date_before_start_date()
    {
        // Arrange
        $startDate = '2024-01-05';
        $endDate = '2024-01-01';
        
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('End date must be after start date');
        
        // Act
        $this->leaveService->calculateLeaveDays($startDate, $endDate);
    }

    /** @test */
    public function it_excludes_weekends_from_leave_calculation()
    {
        // Arrange
        $startDate = '2024-01-01'; // Monday
        $endDate = '2024-01-07';   // Sunday
        
        // Act
        $businessDays = $this->leaveService->calculateBusinessDays($startDate, $endDate);
        
        // Assert
        $this->assertEquals(5, $businessDays); // Exclude Sat & Sun
    }

    /** @test */
    public function it_checks_leave_balance_before_approval()
    {
        // Arrange
        $employee = new Employee(['leave_balance' => 5]);
        $requestedDays = 10;
        
        // Act
        $hasBalance = $this->leaveService->hasLeaveBalance($employee, $requestedDays);
        
        // Assert
        $this->assertFalse($hasBalance);
    }
}
```

---

### **2. Integration Testing (Gray Box)** 🔗

**Definisi:** Testing interaksi antara multiple components (Controllers, Services, Repositories, Database).

**Karakteristik:**
- ✅ Test real database interactions
- ✅ Verify data flow between layers
- ✅ Catch integration bugs
- ✅ Use test database
- ✅ Transaction rollback

**Example Use Cases:**
```php
// Test component interactions
- Controller → Service → Repository → Database
- API endpoint → Business logic → Data persistence
- Authentication flow
- Authorization checks
- Database transactions
```

**Contoh Implementasi:**
```php
// tests/Feature/LeaveRequestTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function employee_can_create_leave_request()
    {
        // Arrange
        $employee = Employee::factory()->create([
            'leave_balance' => 10
        ]);
        
        $leaveData = [
            'leave_type' => 'annual',
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-05',
            'reason' => 'Family vacation'
        ];

        // Act
        $response = $this->actingAs($employee, 'api')
            ->postJson('/api/leaves', $leaveData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'employee_id',
                    'leave_type',
                    'start_date',
                    'end_date',
                    'total_days',
                    'status'
                ]
            ]);

        // Verify database
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $employee->id,
            'leave_type' => 'annual',
            'status' => 'pending'
        ]);

        // Verify leave balance updated
        $this->assertEquals(5, $employee->fresh()->leave_balance);
    }

    /** @test */
    public function employee_cannot_create_leave_with_insufficient_balance()
    {
        // Arrange
        $employee = Employee::factory()->create([
            'leave_balance' => 2
        ]);
        
        $leaveData = [
            'leave_type' => 'annual',
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-10', // 10 days
            'reason' => 'Vacation'
        ];

        // Act
        $response = $this->actingAs($employee, 'api')
            ->postJson('/api/leaves', $leaveData);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Insufficient leave balance'
            ]);

        // Verify no record created
        $this->assertDatabaseMissing('leave_requests', [
            'employee_id' => $employee->id
        ]);
    }

    /** @test */
    public function manager_can_approve_subordinate_leave()
    {
        // Arrange
        $manager = Employee::factory()->create();
        $subordinate = Employee::factory()->create([
            'manager_id' => $manager->id
        ]);
        
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $subordinate->id,
            'status' => 'pending'
        ]);

        // Act
        $response = $this->actingAs($manager, 'api')
            ->postJson("/api/approvals/level-1/{$leaveRequest->id}", [
                'status' => 'approved',
                'notes' => 'Approved for vacation'
            ]);

        // Assert
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved_l1'
        ]);

        $this->assertDatabaseHas('leave_approval_history', [
            'leave_request_id' => $leaveRequest->id,
            'approver_id' => $manager->id,
            'action' => 'approved'
        ]);
    }
}
```

---

### **3. API Testing (Contract Testing)** 📡

**Definisi:** Testing API endpoints dari perspektif consumer/client.

**Karakteristik:**
- ✅ Test HTTP methods (GET, POST, PUT, DELETE)
- ✅ Verify response structure
- ✅ Check status codes
- ✅ Validate headers
- ✅ Test authentication & authorization

**Tools:** Postman, Insomnia, PHPUnit, Pest

**Contoh Implementasi:**
```php
// tests/Feature/Api/AuthenticationTest.php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        // Arrange
        $employee = Employee::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123')
        ]);

        // Act
        $response = $this->postJson('/api/login', [
            'email' => 'john@example.com',
            'password' => 'password123'
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in'
            ]);
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        // Act
        $response = $this->postJson('/api/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword'
        ]);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized'
            ]);
    }

    /** @test */
    public function authenticated_user_can_access_protected_routes()
    {
        // Arrange
        $employee = Employee::factory()->create();
        $token = auth('api')->login($employee);

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->getJson('/api/me');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'id' => $employee->id,
                'email' => $employee->email
            ]);
    }
}
```

---


### **4. Database Testing** 🗄️

**Definisi:** Testing database queries, migrations, seeders, dan data integrity.

**Karakteristik:**
- ✅ Test migrations up/down
- ✅ Verify indexes
- ✅ Check foreign key constraints
- ✅ Test complex queries
- ✅ Validate data integrity

**Contoh Implementasi:**
```php
// tests/Unit/Database/MigrationTest.php
<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function employees_table_has_expected_columns()
    {
        $this->assertTrue(Schema::hasTable('employees'));
        
        $columns = [
            'id', 'name', 'email', 'password', 
            'position', 'department', 'leave_balance', 
            'manager_id', 'created_at', 'updated_at'
        ];
        
        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('employees', $column),
                "Column {$column} does not exist in employees table"
            );
        }
    }

    /** @test */
    public function leave_requests_table_has_proper_indexes()
    {
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes('leave_requests');
        
        $indexNames = array_keys($indexes);
        
        $this->assertContains('leave_requests_employee_id_foreign', $indexNames);
        $this->assertContains('leave_requests_employee_id_status_index', $indexNames);
    }

    /** @test */
    public function foreign_key_constraints_are_properly_set()
    {
        $employee = Employee::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $employee->id
        ]);

        // Test cascade delete
        $employee->delete();
        
        // Leave request should be deleted due to cascade
        $this->assertDatabaseMissing('leave_requests', [
            'id' => $leaveRequest->id
        ]);
    }
}

// tests/Unit/Database/QueryPerformanceTest.php
<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class QueryPerformanceTest extends TestCase
{
    /** @test */
    public function attendance_report_query_uses_indexes()
    {
        // Arrange
        $employee = Employee::factory()->create();
        Attendance::factory()->count(100)->create([
            'employee_id' => $employee->id
        ]);

        // Act - Get query execution plan
        DB::enableQueryLog();
        
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', ['2024-01-01', '2024-12-31'])
            ->orderBy('date', 'desc')
            ->get();
        
        $queries = DB::getQueryLog();
        $query = $queries[0]['query'];

        // Assert - Should use index
        $explain = DB::select("EXPLAIN {$query}");
        $this->assertStringContainsString('index', strtolower($explain[0]->Extra));
    }

    /** @test */
    public function no_n_plus_one_queries_when_loading_leaves_with_employees()
    {
        // Arrange
        Employee::factory()->count(10)->create()->each(function ($employee) {
            LeaveRequest::factory()->count(5)->create([
                'employee_id' => $employee->id
            ]);
        });

        // Act
        DB::enableQueryLog();
        
        // Without eager loading - BAD
        $leaves = LeaveRequest::all();
        foreach ($leaves as $leave) {
            $employee = $leave->employee; // N+1 problem!
        }
        
        $badQueryCount = count(DB::getQueryLog());
        
        DB::flushQueryLog();
        
        // With eager loading - GOOD
        $leaves = LeaveRequest::with('employee')->get();
        foreach ($leaves as $leave) {
            $employee = $leave->employee;
        }
        
        $goodQueryCount = count(DB::getQueryLog());

        // Assert
        $this->assertLessThan($badQueryCount, $goodQueryCount);
        $this->assertEquals(1, $goodQueryCount); // Only 1 query with JOIN
    }
}
```

---

### **5. Security Testing** 🔒

**Definisi:** Testing keamanan aplikasi terhadap common vulnerabilities.

**Karakteristik:**
- ✅ Test authentication bypass
- ✅ Authorization checks
- ✅ SQL Injection prevention
- ✅ XSS prevention
- ✅ CSRF protection

**Contoh Implementasi:**
```php
// tests/Feature/Security/AuthorizationTest.php
<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function employee_cannot_approve_own_leave()
    {
        // Arrange
        $employee = Employee::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $employee->id
        ]);

        // Act
        $response = $this->actingAs($employee, 'api')
            ->postJson("/api/approvals/level-1/{$leaveRequest->id}", [
                'status' => 'approved'
            ]);

        // Assert
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'You cannot approve your own leave request'
            ]);
    }

    /** @test */
    public function employee_cannot_view_other_employee_data()
    {
        // Arrange
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        // Act
        $response = $this->actingAs($employee1, 'api')
            ->getJson("/api/employees/{$employee2->id}");

        // Assert
        $response->assertStatus(403);
    }

    /** @test */
    public function api_prevents_sql_injection()
    {
        // Arrange
        $employee = Employee::factory()->create();
        
        // Act - Try SQL injection
        $response = $this->actingAs($employee, 'api')
            ->getJson("/api/employees?search=1' OR '1'='1");

        // Assert - Should not cause error or expose data
        $response->assertStatus(200);
        // Should return empty or safe results, not all data
    }

    /** @test */
    public function mass_assignment_vulnerabilities_are_protected()
    {
        // Arrange
        $employee = Employee::factory()->create(['leave_balance' => 10]);

        // Act - Try to manipulate leave_balance via mass assignment
        $response = $this->actingAs($employee, 'api')
            ->putJson("/api/employees/{$employee->id}", [
                'name' => 'Updated Name',
                'leave_balance' => 999 // Try to hack balance
            ]);

        // Assert
        $employee->refresh();
        $this->assertEquals(10, $employee->leave_balance); // Balance unchanged
        $this->assertEquals('Updated Name', $employee->name); // Name updated
    }
}
```

---

### **6. Performance Testing** ⚡

**Definisi:** Testing response time, throughput, dan scalability.

**Karakteristik:**
- ✅ Load testing
- ✅ Stress testing
- ✅ Query optimization
- ✅ Memory usage
- ✅ Concurrent requests

**Tools:** Apache JMeter, Artillery, k6, Laravel Benchmarking

**Contoh Implementasi:**
```php
// tests/Performance/ApiPerformanceTest.php
<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class ApiPerformanceTest extends TestCase
{
    /** @test */
    public function employee_list_endpoint_responds_within_200ms()
    {
        // Arrange
        Employee::factory()->count(100)->create();
        $employee = Employee::factory()->create();

        // Act
        $startTime = microtime(true);
        
        $response = $this->actingAs($employee, 'api')
            ->getJson('/api/employees');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to ms

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(200, $executionTime, 
            "Endpoint took {$executionTime}ms, expected < 200ms"
        );
    }

    /** @test */
    public function database_queries_are_optimized()
    {
        // Arrange
        $employee = Employee::factory()->create();

        // Act
        DB::enableQueryLog();
        
        $response = $this->actingAs($employee, 'api')
            ->getJson('/api/leaves/my-requests');
        
        $queries = DB::getQueryLog();

        // Assert
        $this->assertLessThanOrEqual(3, count($queries), 
            "Endpoint executed " . count($queries) . " queries, expected <= 3"
        );
    }

    /** @test */
    public function api_can_handle_concurrent_requests()
    {
        // Arrange
        $employees = Employee::factory()->count(10)->create();
        
        // Act - Simulate concurrent requests
        $promises = [];
        foreach ($employees as $employee) {
            $promises[] = $this->actingAs($employee, 'api')
                ->getJson('/api/me');
        }

        // Assert - All should succeed
        foreach ($promises as $response) {
            $response->assertStatus(200);
        }
    }
}
```

---

## 🏗️ Testing Pyramid untuk Backend API

```
                    ▲
                   ╱│╲
                  ╱ │ ╲
                 ╱  │  ╲
                ╱   │   ╲               E2E Tests (5%)
               ╱────┴────╲              - Full workflow
              ╱ End-to-End╲             - UI + API + DB
             ╱─────────────╲            - Slowest
            ╱               ╲           
           ╱                 ╲          
          ╱    Integration    ╲         Integration Tests (30%)
         ╱        Tests        ╲        - API endpoints
        ╱─────────────────────  ╲       - Database
       ╱                         ╲      - Services
      ╱                           ╲     - Medium speed
     ╱                             ╲    
    ╱           Unit Tests          ╲   Unit Tests (65%)
   ╱─────────────────────────────────╲  - Business logic
  ╱___________________________________╲ - Utilities
                                        - Fastest
```

### **Rekomendasi Proporsi:**

| Test Type | Proportion | Execution Time | Focus Area |
|-----------|------------|----------------|------------|
| **Unit Tests** | 65% | < 100ms | Business logic, utilities, calculations |
| **Integration Tests** | 30% | 100-500ms | API endpoints, database, services |
| **E2E Tests** | 5% | > 1s | Critical user workflows |

---

## 🛠️ Testing Tools & Framework

### **1. PHPUnit (Default Laravel)**

```bash
# Install
composer require --dev phpunit/phpunit

# Run tests
php artisan test
./vendor/bin/phpunit

# Run specific test
php artisan test --filter=LeaveRequestTest

# Run with coverage
php artisan test --coverage
```

### **2. Pest PHP (Modern Alternative)**

```bash
# Install
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev

# Initialize
php artisan pest:install

# Run tests
./vendor/bin/pest
```

**Pest Example:**
```php
// tests/Feature/LeaveRequestTest.php
<?php

use App\Models\Employee;
use App\Models\LeaveRequest;

it('allows employee to create leave request', function () {
    $employee = Employee::factory()->create(['leave_balance' => 10]);
    
    $response = $this->actingAs($employee, 'api')
        ->postJson('/api/leaves', [
            'leave_type' => 'annual',
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-05',
            'reason' => 'Vacation'
        ]);

    $response->assertStatus(201);
    
    expect($response->json('data'))
        ->toHaveKey('id')
        ->toHaveKey('status', 'pending');
});

it('rejects leave request with insufficient balance', function () {
    $employee = Employee::factory()->create(['leave_balance' => 2]);
    
    $response = $this->actingAs($employee, 'api')
        ->postJson('/api/leaves', [
            'leave_type' => 'annual',
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-10', // 10 days
            'reason' => 'Vacation'
        ]);

    $response->assertStatus(422);
    
    expect($response->json('error'))
        ->toBe('Insufficient leave balance');
});
```

### **3. Postman / Newman (API Testing)**

```bash
# Install Newman
npm install -g newman

# Run Postman collection
newman run api-tests.json -e environment.json
```

### **4. Laravel Dusk (E2E Browser Testing)**

```bash
# Install
composer require --dev laravel/dusk

# Setup
php artisan dusk:install

# Run
php artisan dusk
```

---

## 📊 Implementation Guide

### **Step 1: Project Structure**

```
tests/
├── Feature/              # Integration & API tests
│   ├── Api/
│   │   ├── AuthenticationTest.php
│   │   ├── EmployeeTest.php
│   │   └── LeaveTest.php
│   ├── Security/
│   │   └── AuthorizationTest.php
│   └── Performance/
│       └── ApiPerformanceTest.php
├── Unit/                 # Unit tests
│   ├── Services/
│   │   ├── LeaveServiceTest.php
│   │   ├── AttendanceServiceTest.php
│   │   └── EmployeeServiceTest.php
│   ├── Repositories/
│   │   └── LeaveRepositoryTest.php
│   └── Database/
│       ├── MigrationTest.php
│       └── QueryPerformanceTest.php
├── TestCase.php          # Base test class
└── CreatesApplication.php
```

### **Step 2: Base Test Class**

```php
// tests/TestCase.php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed test data
        $this->seed();
        
        // Disable events if needed
        // Event::fake();
    }

    /**
     * Helper: Create authenticated employee
     */
    protected function authenticatedEmployee($attributes = [])
    {
        $employee = Employee::factory()->create($attributes);
        $this->actingAs($employee, 'api');
        return $employee;
    }

    /**
     * Helper: Assert JSON validation error
     */
    protected function assertValidationError($response, $field)
    {
        $response->assertStatus(422)
            ->assertJsonValidationErrors($field);
    }
}
```

### **Step 3: Factory & Seeders untuk Testing**

```php
// database/factories/EmployeeFactory.php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EmployeeFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'position' => $this->faker->jobTitle(),
            'department' => $this->faker->randomElement(['IT', 'HR', 'Finance', 'Sales']),
            'leave_balance' => $this->faker->numberBetween(5, 15),
            'manager_id' => null,
        ];
    }

    /**
     * Employee with manager
     */
    public function withManager()
    {
        return $this->state(function (array $attributes) {
            return [
                'manager_id' => Employee::factory(),
            ];
        });
    }

    /**
     * Employee with low leave balance
     */
    public function lowBalance()
    {
        return $this->state(function (array $attributes) {
            return [
                'leave_balance' => $this->faker->numberBetween(0, 2),
            ];
        });
    }
}
```

---


### **Step 4: Running Tests**

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run specific test file
php artisan test tests/Feature/LeaveRequestTest.php

# Run specific test method
php artisan test --filter=test_employee_can_create_leave_request

# Run with coverage report
php artisan test --coverage --min=80

# Run in parallel (faster)
php artisan test --parallel

# Run with detailed output
php artisan test --verbose
```

---

## 🎯 Best Practices

### **1. AAA Pattern (Arrange-Act-Assert)**

```php
/** @test */
public function it_calculates_leave_days_correctly()
{
    // Arrange - Setup test data
    $startDate = '2024-01-01';
    $endDate = '2024-01-05';
    $service = new LeaveService();
    
    // Act - Execute the action
    $result = $service->calculateLeaveDays($startDate, $endDate);
    
    // Assert - Verify the result
    $this->assertEquals(5, $result);
}
```

### **2. Test Naming Convention**

```php
// ✅ Good - Descriptive names
public function test_employee_can_create_leave_request()
public function test_employee_cannot_create_leave_with_insufficient_balance()
public function test_manager_can_approve_subordinate_leave()

// ❌ Bad - Unclear names
public function test1()
public function testLeave()
public function testCreate()
```

### **3. Use Data Providers untuk Multiple Scenarios**

```php
/**
 * @test
 * @dataProvider leaveTypeProvider
 */
public function it_validates_leave_types($type, $isValid)
{
    $validator = new LeaveValidator();
    $result = $validator->isValidType($type);
    
    $this->assertEquals($isValid, $result);
}

public function leaveTypeProvider()
{
    return [
        'annual leave' => ['annual', true],
        'sick leave' => ['sick', true],
        'personal leave' => ['personal', true],
        'invalid type' => ['vacation', false],
        'empty string' => ['', false],
    ];
}
```

### **4. Mock External Dependencies**

```php
/** @test */
public function it_sends_notification_when_leave_approved()
{
    // Mock notification service
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('send')
        ->once()
        ->with(Mockery::type(Employee::class), 'leave_approved')
        ->andReturn(true);
    
    $this->app->instance(NotificationService::class, $notificationService);
    
    // Test leave approval
    $leaveService = app(LeaveService::class);
    $leaveService->approveLeave($leaveRequest);
}
```

### **5. Database Transaction & Cleanup**

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase; // Auto rollback after each test

    /** @test */
    public function it_creates_leave_request()
    {
        // Test will automatically rollback
        $leave = LeaveRequest::create([...]);
        
        $this->assertDatabaseHas('leave_requests', [...]);
    }
}
```

### **6. Test Edge Cases**

```php
/** @test */
public function it_handles_edge_cases()
{
    // Null values
    $this->expectException(\InvalidArgumentException::class);
    $service->calculateLeaveDays(null, '2024-01-05');
    
    // Empty strings
    $this->expectException(\InvalidArgumentException::class);
    $service->calculateLeaveDays('', '2024-01-05');
    
    // Same date
    $result = $service->calculateLeaveDays('2024-01-01', '2024-01-01');
    $this->assertEquals(1, $result);
    
    // Past dates
    $this->expectException(\InvalidArgumentException::class);
    $service->calculateLeaveDays('2023-01-01', '2024-01-01');
}
```

---

## 📈 Code Coverage

### **Generate Coverage Report**

```bash
# HTML coverage report
php artisan test --coverage-html coverage-report

# Terminal coverage
php artisan test --coverage

# Minimum coverage requirement
php artisan test --coverage --min=80
```

### **Coverage Goals**

| Component | Minimum Coverage |
|-----------|-----------------|
| Models | 90% |
| Services | 85% |
| Controllers | 80% |
| Repositories | 85% |
| Helpers/Utils | 95% |
| Overall | 80% |

---

## 🔄 CI/CD Integration

### **GitHub Actions Example**

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: mbstring, pdo, pdo_mysql
          coverage: xdebug

      - name: Install Dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Copy .env
        run: php -r "file_exists('.env') || copy('.env.example', '.env');"

      - name: Generate key
        run: php artisan key:generate

      - name: Run Tests
        run: php artisan test --coverage --min=80
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: Upload Coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

---

## 🐛 Common Testing Pitfalls

### **❌ Don't:**

1. **Test Framework Code**
```php
// ❌ Bad - Testing Laravel, not your code
public function test_model_has_fillable()
{
    $this->assertIsArray($this->model->getFillable());
}
```

2. **Create God Tests**
```php
// ❌ Bad - Testing too many things
public function test_entire_workflow()
{
    // Create employee
    // Create leave
    // Approve leave
    // Check notification
    // Update balance
    // Generate report
    // ... 100 lines of code
}
```

3. **Ignore Test Isolation**
```php
// ❌ Bad - Tests depend on each other
public function test_step_1() { /* creates data */ }
public function test_step_2() { /* uses data from step 1 */ }
```

4. **Hard-code Test Data**
```php
// ❌ Bad - Brittle tests
$employee = Employee::find(1); // What if ID 1 doesn't exist?
```

### **✅ Do:**

1. **Test Your Business Logic**
```php
// ✅ Good - Testing your code
public function test_leave_balance_decreases_after_approval()
{
    $employee = Employee::factory()->create(['leave_balance' => 10]);
    $leave = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'total_days' => 5
    ]);
    
    $this->leaveService->approve($leave);
    
    $this->assertEquals(5, $employee->fresh()->leave_balance);
}
```

2. **Keep Tests Focused**
```php
// ✅ Good - Single responsibility
public function test_employee_can_create_leave() { /* ... */ }
public function test_manager_can_approve_leave() { /* ... */ }
public function test_notification_sent_after_approval() { /* ... */ }
```

3. **Use Factories**
```php
// ✅ Good - Flexible and isolated
$employee = Employee::factory()->create();
$leave = LeaveRequest::factory()->for($employee)->create();
```

---

## 📚 Learning Resources

### **Documentation**
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Pest PHP Documentation](https://pestphp.com/)

### **Books**
- "Test Driven Development: By Example" - Kent Beck
- "Growing Object-Oriented Software, Guided by Tests" - Steve Freeman

### **Video Courses**
- Laracasts: Laravel Testing Series
- Test-Driven Laravel (Adam Wathan)

---

## 🎯 Testing Checklist

### **Before Writing Tests:**
- [ ] Understand the business requirement
- [ ] Identify edge cases
- [ ] Plan test scenarios
- [ ] Setup test data (factories)

### **While Writing Tests:**
- [ ] Follow AAA pattern (Arrange-Act-Assert)
- [ ] Use descriptive test names
- [ ] Test one thing per test
- [ ] Mock external dependencies
- [ ] Handle exceptions

### **After Writing Tests:**
- [ ] All tests pass
- [ ] Code coverage > 80%
- [ ] No flaky tests
- [ ] Tests run in isolation
- [ ] Fast execution (< 1 min for unit tests)

---

## 🚀 Next Steps

1. **Setup Testing Environment**
```bash
composer install
php artisan test
```

2. **Create Test Structure**
```bash
mkdir -p tests/{Unit,Feature}/{Services,Repositories,Api,Security}
```

3. **Write Your First Test**
```bash
php artisan make:test LeaveServiceTest --unit
```

4. **Run and Iterate**
```bash
php artisan test --filter=LeaveServiceTest
```

5. **Monitor Coverage**
```bash
php artisan test --coverage --min=80
```

---

## 📊 Summary

| Testing Type | When to Use | Coverage Target | Tools |
|--------------|-------------|-----------------|-------|
| **Unit Testing** | Business logic, utilities | 65% | PHPUnit, Pest |
| **Integration Testing** | API endpoints, DB operations | 30% | PHPUnit, RefreshDatabase |
| **API Testing** | Contract validation | Included in Integration | Postman, PHPUnit |
| **Security Testing** | Auth, Authorization, Injection | Critical paths | PHPUnit, Static Analysis |
| **Performance Testing** | Response time, queries | Critical endpoints | Benchmarking tools |

---

## ✅ Conclusion

**Black Box Testing alone is NOT sufficient** for backend API development because:

❌ Cannot detect internal bugs (logic errors, memory leaks)
❌ Cannot verify database integrity
❌ Cannot catch security vulnerabilities
❌ Slow and expensive to maintain

**Best Approach: Multi-layered Testing Strategy**

✅ **Unit Tests (65%)** - Fast, isolated, test business logic
✅ **Integration Tests (30%)** - Verify component interactions
✅ **API Tests** - Validate contracts
✅ **Security Tests** - Prevent vulnerabilities
✅ **Performance Tests** - Ensure scalability

This comprehensive strategy ensures:
- **High code quality** ✨
- **Fast feedback loop** ⚡
- **Confident deployments** 🚀
- **Maintainable codebase** 🛠️

---

**Questions?** Contact Testing Team: qa-team@company.com

**Happy Testing! 🧪✨**
