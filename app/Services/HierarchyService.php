<?php

namespace App\Services;

use App\Repositories\EmployeeRepository;
use Exception;

class HierarchyService
{
    private $repository;

    public function __construct(EmployeeRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Assign a manager to an employee, with circular reference checking.
     */
    public function assignManager($employeeId, $managerId)
    {
        if ($employeeId == $managerId) {
            throw new Exception("An employee cannot be their own manager.");
        }

        // Circular reference check
        if ($managerId !== null && $this->isCircularReference($employeeId, $managerId)) {
            throw new Exception("Circular reference detected. Cannot assign this manager as they are already a subordinate of the employee.");
        }

        return $this->repository->updateManager($employeeId, $managerId);
    }

    /**
     * Remove a manager from an employee
     */
    public function removeManager($employeeId)
    {
        return $this->repository->updateManager($employeeId, null);
    }

    /**
     * Bulk assign subordinates to a manager
     */
    public function addSubordinates($managerId, array $subordinateIds)
    {
        // Remove managerId from subordinateIds if mistakenly included
        $subordinateIds = array_diff($subordinateIds, [$managerId]);

        foreach ($subordinateIds as $subId) {
            if ($this->isCircularReference($subId, $managerId)) {
                throw new Exception("Circular reference detected for subordinate ID $subId. Operation aborted.");
            }
        }

        return $this->repository->updateManagerBulk($subordinateIds, $managerId);
    }

    /**
     * Check if assigning $managerId as manager for $employeeId creates a loop
     * It checks if $managerId is actually a subordinate of $employeeId
     */
    private function isCircularReference($employeeId, $managerId)
    {
        // Trace up from the proposed managerId. If we ever hit employeeId, it's a loop.
        $currentManager = $this->repository->getEmployeeById($managerId);

        while ($currentManager && $currentManager->manager_id !== null) {
            if ($currentManager->manager_id == $employeeId) {
                return true;
            }
            $currentManager = $this->repository->getEmployeeById($currentManager->manager_id);
        }

        return false;
    }
}
