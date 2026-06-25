<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optimalisasi Database — Indexing pada tabel Attendances
     *
     * MASALAH:
     * Tabel `attendances` sudah memiliki UNIQUE(employee_id, date) dan INDEX(date),
     * namun query paling kritis (clock-in/out lookup, date range + status filtering)
     * membutuhkan composite index yang mencakup kolom `status` untuk menghindari
     * Full Table Scan saat melakukan agregasi summary.
     *
     * SOLUSI:
     * Composite index (employee_id, date, status) memungkinkan:
     * 1. Clock-in/out lookup: WHERE employee_id = ? AND date = ? → Index Seek
     * 2. Date range query: WHERE employee_id = ? AND date BETWEEN → Index Range Scan
     * 3. Summary aggregation: COUNT + GROUP BY status → Covering Index (tanpa akses tabel)
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Composite index untuk mempercepat:
            // - findByEmployeeAndDate() → WHERE employee_id = ? AND date = ?
            // - getByEmployeeAndDateRange() → WHERE employee_id = ? AND date BETWEEN ? AND ?
            // - getEmployeeSummary() → + GROUP BY status (covering index)
            $table->index(
                ['employee_id', 'date', 'status'],
                'idx_attendances_employee_date_status'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_employee_date_status');
        });
    }
};
