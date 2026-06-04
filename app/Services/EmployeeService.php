<?php

namespace App\Services;

use App\Repositories\EmployeeRepository;

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
     */
    public function getEmployeeById($id)
    {
        return $this->repository->getEmployeeById($id);
    }

    /**
     * Create a new employee record.
     */
    public function createEmployee(array $data)
    {
        return $this->repository->createEmployee($data);
    }

    /**
     * Update an existing employee record.
     */
    public function updateEmployee($id, array $data)
    {
        return $this->repository->updateEmployee($id, $data);
    }

    /**
     * Delete an employee.
     */
    public function deleteEmployee($id)
    {
        return $this->repository->deleteEmployee($id);
    }

    /**
     * Get direct subordinates of a manager.
     */
    public function getSubordinates($managerId)
    {
        return $this->repository->getSubordinates($managerId);
    }

    /**
     * Get employee hierarchy (employee + manager + subordinates).
     */
    public function getEmployeeHierarchy($id)
    {
        return $this->repository->getEmployeeHierarchy($id);
    }

    /**
     * Update leave balance of an employee.
     */
    public function updateLeaveBalance($id, $leaveBalance)
    {
        return $this->repository->updateLeaveBalance($id, $leaveBalance);
    }

    /**
     * Get employee statistics.
     */
    public function getStatistics()
    {
        return $this->repository->getStatistics();
    }
}
