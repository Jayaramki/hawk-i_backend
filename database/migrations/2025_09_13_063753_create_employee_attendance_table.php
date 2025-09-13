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
        Schema::create('employee_attendance', function (Blueprint $table) {
            $table->id();
            $table->date('attendance_date');
            $table->string('ina_employee_id');
            $table->string('employee_name');
            $table->string('department')->nullable();
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->timestamps();

            // Add indexes for better query performance
            $table->index('attendance_date');
            $table->index('ina_employee_id');
            $table->index(['ina_employee_id', 'attendance_date']);
            $table->index('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_attendance');
    }
};