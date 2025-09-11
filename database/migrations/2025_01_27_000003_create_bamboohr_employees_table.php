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
        Schema::create('bamboohr_employees', function (Blueprint $table) {
            $table->id();
            $table->string('bamboohr_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('job_title')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('status')->default('active');
            $table->string('work_email')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('work_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('sync_status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('bamboohr_departments')->onDelete('set null');
            $table->foreign('supervisor_id')->references('id')->on('bamboohr_employees')->onDelete('set null');
            $table->index(['bamboohr_id', 'sync_status']);
            $table->index(['department_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bamboohr_employees');
    }
};
