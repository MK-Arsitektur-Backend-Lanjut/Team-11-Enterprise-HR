<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optimalisasi Database — Indexing pada tabel Approval Workflow
     *
     * Kolom yang DI-INDEX (high cardinality):
     * - employee_id   → Ribuan nilai unik, dipakai di hampir semua query
     * - approver_id   → Setiap manager punya ID unik
     * - created_at    → Unik per detik, dipakai untuk ORDER BY
     * - start_date + end_date → 365 nilai unik/tahun, dipakai untuk query range tanggal
     *
     * Kolom yang TIDAK DI-INDEX:
     * - status        → Low cardinality (hanya 3 nilai: pending/approved/rejected)
     * - id            → Sudah otomatis punya Primary Key Index (Clustered Index)
     * - approval_level → Low cardinality (hanya 2 nilai: 1 dan 2)
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Index untuk WHERE employee_id = ? (dipakai di getRequestsByEmployee, submitLeaveRequest, dll)
            $table->index('employee_id', 'idx_leave_requests_employee_id');

            // Index untuk ORDER BY created_at DESC (dipakai di semua list endpoint)
            $table->index('created_at', 'idx_leave_requests_created_at');

            // Composite index untuk query range tanggal (dipakai di getByEmployeeAndDateRange, getApprovedLeavesSummary)
            $table->index(['start_date', 'end_date'], 'idx_leave_requests_date_range');
        });

        Schema::table('leave_approvals', function (Blueprint $table) {
            // Index untuk WHERE approver_id = ? (dipakai di getPendingApprovalsFor, processApproval)
            $table->index('approver_id', 'idx_leave_approvals_approver_id');

            // Index untuk ORDER BY created_at (jika dibutuhkan untuk sorting riwayat approval)
            $table->index('created_at', 'idx_leave_approvals_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex('idx_leave_requests_employee_id');
            $table->dropIndex('idx_leave_requests_created_at');
            $table->dropIndex('idx_leave_requests_date_range');
        });

        Schema::table('leave_approvals', function (Blueprint $table) {
            $table->dropIndex('idx_leave_approvals_approver_id');
            $table->dropIndex('idx_leave_approvals_created_at');
        });
    }
};
