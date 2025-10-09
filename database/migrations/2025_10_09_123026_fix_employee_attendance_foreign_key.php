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
        Schema::table('employee_attendance', function (Blueprint $table) {
            // Drop the incorrect foreign key constraint
            $table->dropForeign(['ina_employee_id']);
            
            // Add the correct foreign key constraint
            $table->foreign('ina_employee_id')->references('id')->on('inatech_employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_attendance', function (Blueprint $table) {
            // Drop the correct foreign key constraint
            $table->dropForeign(['ina_employee_id']);
            
            // Restore the incorrect foreign key constraint (for rollback)
            $table->foreign('ina_employee_id')->references('id')->on('bamboohr_employees')->onDelete('cascade');
        });
    }
};
