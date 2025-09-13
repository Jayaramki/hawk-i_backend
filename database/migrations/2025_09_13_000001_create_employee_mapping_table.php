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
        Schema::create('employee_mapping', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->integer('ina_emp_id')->comment('INA employee ID');
            $table->string('bamboohr_id')->nullable()->comment('BambooHR employee ID');
            $table->string('ado_user_id')->nullable()->comment('Azure DevOps user ID');
            $table->timestamps();
            
            // Add indexes for better query performance
            $table->index('ina_emp_id');
            $table->index('bamboohr_id');
            $table->index('ado_user_id');
            
            // Add unique constraint on ina_emp_id to ensure one mapping per INA employee
            $table->unique('ina_emp_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_mapping');
    }
};