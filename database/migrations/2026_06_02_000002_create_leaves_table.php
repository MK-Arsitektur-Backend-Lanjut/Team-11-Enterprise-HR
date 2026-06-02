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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('leave_type'); // annual, sick, personal, maternity, etc.
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable(); // ID dari manager/HR yang approve
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('external_leave_id')->nullable(); // ID referensi dari Approval Service
            $table->timestamps();

            $table->index(['employee_id', 'status']); // Index untuk query cuti per employee & status
            $table->index(['start_date', 'end_date']); // Index untuk query per periode
            $table->index(['external_leave_id']); // Index untuk sync dari Approval Service
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
