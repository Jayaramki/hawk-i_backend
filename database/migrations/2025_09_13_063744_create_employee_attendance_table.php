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
            $table->unsignedBigInteger('ina_employee_id');
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('ina_employee_id')->references('id')->on('bamboohr_employees')->onDelete('cascade');
            
            // Indexes for better performance
            $table->index(['attendance_date', 'ina_employee_id']);
            $table->index(['attendance_date']);
            $table->index(['ina_employee_id']);
            
            // Unique constraint to prevent duplicate attendance records for the same employee on the same date
            $table->unique(['attendance_date', 'ina_employee_id'], 'unique_employee_date');
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