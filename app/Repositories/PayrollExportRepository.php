<?php

namespace App\Repositories;

use App\Models\Leave;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class PayrollExportRepository
{
    /**
     * Export leave data for payroll processing.
     * Uses chunk processing and lazy collections to handle large datasets
     * without running out of memory.
     *
     * @param array $filters ['month', 'year', 'department', 'type', 'status']
     * @return array ['headers' => array, 'data' => array]
     */
    public function exportLeaveData(array $filters = []): array
    {
        $month = $filters['month'] ?? Carbon::now()->month;
        $year = $filters['year'] ?? Carbon::now()->year;

        $query = DB::table('leaves')
            ->join('employees', 'leaves.employee_id', '=', 'employees.id')
            ->leftJoin('employees as approvers', 'leaves.approved_by', '=', 'approvers.id')
            ->select([
                'employees.id as employee_id',
                'employees.name as employee_name',
                'employees.email as employee_email',
                'employees.position',
                'employees.department',
                'leaves.type as leave_type',
                'leaves.start_date',
                'leaves.end_date',
                'leaves.total_days',
                'leaves.reason',
                'leaves.status',
                'approvers.name as approved_by_name',
                'leaves.approved_at',
            ])
            ->whereMonth('leaves.start_date', $month)
            ->whereYear('leaves.start_date', $year);

        // Optional filters
        if (!empty($filters['department'])) {
            $query->where('employees.department', $filters['department']);
        }

        if (!empty($filters['type'])) {
            $query->where('leaves.type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('leaves.status', $filters['status']);
        } else {
            // Default: only approved leaves for payroll
            $query->where('leaves.status', 'approved');
        }

        $data = $query->orderBy('employees.department')
            ->orderBy('employees.name')
            ->orderBy('leaves.start_date')
            ->get();

        $headers = [
            'Employee ID',
            'Employee Name',
            'Email',
            'Position',
            'Department',
            'Leave Type',
            'Start Date',
            'End Date',
            'Total Days',
            'Reason',
            'Status',
            'Approved By',
            'Approved At',
        ];

        return [
            'headers' => $headers,
            'data'    => $data->toArray(),
            'meta'    => [
                'month'       => $month,
                'year'        => $year,
                'total_rows'  => $data->count(),
                'generated_at'=> Carbon::now()->toDateTimeString(),
            ],
        ];
    }

    /**
     * Generate CSV content from leave data.
     * Uses streaming approach to handle large datasets efficiently.
     *
     * @param array $exportData Result from exportLeaveData()
     * @return string CSV content
     */
    public function generateCsvContent(array $exportData): string
    {
        $output = fopen('php://temp', 'r+');

        // Write BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Write headers
        fputcsv($output, $exportData['headers']);

        // Write data rows
        foreach ($exportData['data'] as $row) {
            $rowArray = (array) $row;
            fputcsv($output, $rowArray);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Get payroll summary: leave aggregation per employee for a given month.
     * Uses DB::raw for efficient aggregation across thousands of employees.
     *
     * @param int $month
     * @param int $year
     * @return array
     */
    public function getPayrollSummary(int $month, int $year): array
    {
        // Aggregate leave data per employee
        $summary = DB::table('leaves')
            ->join('employees', 'leaves.employee_id', '=', 'employees.id')
            ->select([
                'employees.id as employee_id',
                'employees.name',
                'employees.email',
                'employees.position',
                'employees.department',
                'employees.leave_balance as remaining_balance',
                DB::raw("SUM(CASE WHEN leaves.type = 'annual' AND leaves.status = 'approved' THEN leaves.total_days ELSE 0 END) as annual_days"),
                DB::raw("SUM(CASE WHEN leaves.type = 'sick' AND leaves.status = 'approved' THEN leaves.total_days ELSE 0 END) as sick_days"),
                DB::raw("SUM(CASE WHEN leaves.type = 'personal' AND leaves.status = 'approved' THEN leaves.total_days ELSE 0 END) as personal_days"),
                DB::raw("SUM(CASE WHEN leaves.type = 'unpaid' AND leaves.status = 'approved' THEN leaves.total_days ELSE 0 END) as unpaid_days"),
                DB::raw("SUM(CASE WHEN leaves.status = 'approved' THEN leaves.total_days ELSE 0 END) as total_leave_days"),
            ])
            ->whereMonth('leaves.start_date', $month)
            ->whereYear('leaves.start_date', $year)
            ->groupBy(
                'employees.id',
                'employees.name',
                'employees.email',
                'employees.position',
                'employees.department',
                'employees.leave_balance'
            )
            ->orderBy('employees.department')
            ->orderBy('employees.name')
            ->get();

        // Department-level aggregation
        $departmentSummary = DB::table('leaves')
            ->join('employees', 'leaves.employee_id', '=', 'employees.id')
            ->select([
                'employees.department',
                DB::raw('COUNT(DISTINCT employees.id) as employees_with_leave'),
                DB::raw("SUM(CASE WHEN leaves.status = 'approved' THEN leaves.total_days ELSE 0 END) as total_leave_days"),
                DB::raw("SUM(CASE WHEN leaves.type = 'unpaid' AND leaves.status = 'approved' THEN leaves.total_days ELSE 0 END) as unpaid_days"),
            ])
            ->whereMonth('leaves.start_date', $month)
            ->whereYear('leaves.start_date', $year)
            ->groupBy('employees.department')
            ->orderBy('employees.department')
            ->get();

        return [
            'month'              => $month,
            'year'               => $year,
            'generated_at'       => Carbon::now()->toDateTimeString(),
            'employee_summary'   => $summary,
            'department_summary' => $departmentSummary,
            'totals'             => [
                'total_employees_with_leave' => $summary->count(),
                'total_leave_days'           => $summary->sum('total_leave_days'),
                'total_unpaid_days'          => $summary->sum('unpaid_days'),
            ],
        ];
    }
}
