<?php

namespace App\Services;

use App\Repositories\EmployeeRepository;
use Illuminate\Support\Facades\Cache;

class EmployeeService
{
    protected $repository;

    public function __construct(EmployeeRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get paginated list of employees with filters.
     */
    public function getPaginatedEmployees($perPage, $filters = [])
    {
        return $this->repository->getPaginatedEmployees($perPage, $filters);
    }

    /**
     * Get employee details by ID.
     * Results are cached for 10 minutes.
     */
    public function getEmployeeById($id)
    {
        $cacheKey = "employee:{$id}";

        return Cache::remember($cacheKey, now()->addMinutes(CacheService::DEFAULT_TTL), function () use ($id) {
            return $this->repository->getEmployeeById($id);
        });
    }

    /**
     * Create a new employee record.
     */
    public function createEmployee(array $data)
    {
        $employee = $this->repository->createEmployee($data);

        // Invalidate statistics cache after creating employee
        Cache::forget('employee:statistics');

        return $employee;
    }

    /**
     * Update an existing employee record.
     */
    public function updateEmployee($id, array $data)
    {
        $employee = $this->repository->updateEmployee($id, $data);

        // Invalidate cache for the updated employee
        CacheService::invalidateEmployee($id);

        return $employee;
    }

    /**
     * Delete an employee.
     */
    public function deleteEmployee($id)
    {
        $result = $this->repository->deleteEmployee($id);

        // Invalidate cache for the deleted employee
        CacheService::invalidateEmployee($id);

        return $result;
    }

    /**
     * Get direct subordinates of a manager.
     * Results are cached for 10 minutes.
     */
    public function getSubordinates($managerId)
    {
        $cacheKey = "employee:subordinates:{$managerId}";

        return Cache::remember($cacheKey, now()->addMinutes(CacheService::DEFAULT_TTL), function () use ($managerId) {
            return $this->repository->getSubordinates($managerId);
        });
    }

    /**
     * Get employee hierarchy (employee + manager + subordinates).
     * Results are cached for 10 minutes.
     */
    public function getEmployeeHierarchy($id)
    {
        $cacheKey = "employee:hierarchy:{$id}";

        return Cache::remember($cacheKey, now()->addMinutes(CacheService::DEFAULT_TTL), function () use ($id) {
            return $this->repository->getEmployeeHierarchy($id);
        });
    }

    /**
     * Update leave balance of an employee.
     */
    public function updateLeaveBalance($id, $leaveBalance)
    {
        $result = $this->repository->updateLeaveBalance($id, $leaveBalance);

        // Invalidate employee and leave balance cache
        CacheService::invalidateEmployee($id);

        return $result;
    }

    /**
     * Get employee statistics.
     * Results are cached for 5 minutes.
     */
    public function getStatistics()
    {
        return Cache::remember('employee:statistics', now()->addMinutes(CacheService::MEDIUM_TTL), function () {
            return $this->repository->getStatistics();
        });
    }
}
