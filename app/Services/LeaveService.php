<?php

namespace App\Services;

use App\Repositories\LeaveRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeaveService
{
    protected LeaveRepository $leaveRepository;

    public function __construct(LeaveRepository $leaveRepository)
    {
        $this->leaveRepository = $leaveRepository;
    }

    /**
     * Sync leave data from Approval Workflow Service.
     * Pulls approved/rejected/pending leaves from the external service
     * and upserts them into the local database.
     *
     * @param int|null $employeeId Optional: sync for specific employee only
     * @return array
     */
    public function syncFromApprovalService(?int $employeeId = null): array
    {
        $approvalServiceUrl = config('services.approval.url', env('APPROVAL_SERVICE_URL'));

        if (!$approvalServiceUrl) {
            return [
                'success' => false,
                'message' => 'APPROVAL_SERVICE_URL is not configured.',
                'synced_count' => 0,
            ];
        }

        try {
            $url = rtrim($approvalServiceUrl, '/') . '/leaves';
            $params = [];

            if ($employeeId) {
                $params['employee_id'] = $employeeId;
            }

            $response = Http::timeout(30)->get($url, $params);

            if (!$response->successful()) {
                Log::warning('Failed to sync leaves from Approval Service', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Gagal mengambil data dari Approval Service. Status: ' . $response->status(),
                    'synced_count' => 0,
                ];
            }

            $leaves = $response->json('data', []);
            $syncedCount = 0;

            foreach ($leaves as $leaveData) {
                $this->leaveRepository->syncFromExternal(
                    $leaveData['id'], // external leave ID from Approval Service
                    [
                        'employee_id' => $leaveData['employee_id'],
                        'leave_type' => $leaveData['leave_type'] ?? 'annual',
                        'start_date' => $leaveData['start_date'],
                        'end_date' => $leaveData['end_date'],
                        'total_days' => $leaveData['total_days'] ?? $this->calculateTotalDays(
                            $leaveData['start_date'],
                            $leaveData['end_date']
                        ),
                        'status' => $leaveData['status'] ?? 'pending',
                        'reason' => $leaveData['reason'] ?? null,
                        'approved_by' => $leaveData['approved_by'] ?? null,
                        'approved_at' => $leaveData['approved_at'] ?? null,
                    ]
                );
                $syncedCount++;

                // Invalidate cache for each synced employee
                if (isset($leaveData['employee_id'])) {
                    CacheService::invalidateLeave($leaveData['employee_id']);
                }
            }

            // Invalidate all leaves cache after sync
            CacheService::invalidateAllLeaves();

            return [
                'success' => true,
                'message' => "Berhasil sinkronisasi {$syncedCount} data cuti dari Approval Service.",
                'synced_count' => $syncedCount,
            ];

        } catch (\Exception $e) {
            Log::error('Error syncing leaves from Approval Service', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error saat sinkronisasi: ' . $e->getMessage(),
                'synced_count' => 0,
            ];
        }
    }

    /**
     * Get leave records for a specific employee.
     * Results are cached for 5 minutes.
     *
     * @param int $employeeId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEmployeeLeaves(int $employeeId)
    {
        $cacheKey = "leaves:employee:{$employeeId}";

        return Cache::remember($cacheKey, now()->addMinutes(CacheService::MEDIUM_TTL), function () use ($employeeId) {
            return $this->leaveRepository->getByEmployee($employeeId);
        });
    }

    /**
     * Get leave detail by ID.
     *
     * @param int $id
     * @return \App\Models\Leave|null
     */
    public function getLeaveById(int $id)
    {
        return $this->leaveRepository->findById($id);
    }

    /**
     * Get all leaves with optional filters.
     * Results are cached for 3 minutes.
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllLeaves(array $filters = [])
    {
        $filterHash = md5(json_encode($filters));
        $cacheKey = "leaves:all:{$filterHash}";

        return Cache::remember($cacheKey, now()->addMinutes(CacheService::SHORT_TTL), function () use ($filters) {
            return $this->leaveRepository->getAllWithFilters($filters);
        });
    }

    /**
     * Get leave data for payroll export within a custom date range.
     * Results are cached for 10 minutes.
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getPayrollLeaveData(string $startDate, string $endDate): array
    {
        $cacheKey = "leaves:payroll:{$startDate}:{$endDate}";

        return Cache::remember($cacheKey, now()->addMinutes(CacheService::DEFAULT_TTL), function () use ($startDate, $endDate) {
            $leaves = $this->leaveRepository->getApprovedLeavesSummary($startDate, $endDate);

            // Group by employee for payroll summary
            $grouped = $leaves->groupBy('employee_id')->map(function ($employeeLeaves) {
                $employee = $employeeLeaves->first()->employee;

                $leaveByType = $employeeLeaves->groupBy('leave_type')->map(function ($typeLeaves) {
                    return $typeLeaves->sum('total_days');
                });

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'email' => $employee->email,
                    'department' => $employee->department,
                    'position' => $employee->position,
                    'total_leave_days' => $employeeLeaves->sum('total_days'),
                    'leave_breakdown' => $leaveByType,
                    'leave_details' => $employeeLeaves->map(function ($leave) {
                        return [
                            'leave_type' => $leave->leave_type,
                            'start_date' => $leave->start_date->format('Y-m-d'),
                            'end_date' => $leave->end_date->format('Y-m-d'),
                            'total_days' => $leave->total_days,
                            'reason' => $leave->reason,
                        ];
                    }),
                ];
            });

            return [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'total_employees_with_leave' => $grouped->count(),
                'total_leave_days' => $leaves->sum('total_days'),
                'data' => $grouped->values(),
            ];
        });
    }

    /**
     * Calculate total days between two dates.
     *
     * @param string $startDate
     * @param string $endDate
     * @return int
     */
    private function calculateTotalDays(string $startDate, string $endDate): int
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        return $start->diffInDays($end) + 1; // Inclusive
    }
}
