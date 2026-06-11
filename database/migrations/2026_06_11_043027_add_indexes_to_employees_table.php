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
        Schema::table('employees', function (Blueprint $table) {
            $table->index('manager_id');
            $table->index('department');
            $table->index('position');
            // Using fulltext for wildcard searching
            $table->fullText(['name', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['manager_id']);
            $table->dropIndex(['department']);
            $table->dropIndex(['position']);
            $table->dropFullText(['name', 'email']);
        });
    }
};
