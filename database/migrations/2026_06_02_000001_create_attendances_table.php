<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->enum('status', ['present', 'late', 'absent', 'leave', 'half_day'])->default('absent');
            $table->integer('late_minutes')->default(0); // Menit keterlambatan dari jam 08:00
            $table->decimal('work_hours', 5, 2)->default(0); // Total jam kerja
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']); // 1 record per employee per hari
            $table->index(['date']); // Index untuk query per tanggal
            $table->index(['status']); // Index untuk filter status
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
