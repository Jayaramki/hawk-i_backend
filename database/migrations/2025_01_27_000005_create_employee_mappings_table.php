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
        Schema::create('employee_mappings', function (Blueprint $table) {
            $table->id(); // Auto increment primary key
            $table->integer('ina_emp_id'); // Integer field for INA employee ID
            $table->string('bamboohr_id')->nullable(); // VARCHAR field for BambooHR ID
            $table->string('ado_user_id')->nullable(); // VARCHAR field for Azure DevOps user ID
            $table->timestamps();

            // Add indexes for better performance
            $table->index('ina_emp_id');
            $table->index('bamboohr_id');
            $table->index('ado_user_id');
            
            // Add unique constraint to prevent duplicate mappings
            $table->unique('ina_emp_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_mappings');
    }
};