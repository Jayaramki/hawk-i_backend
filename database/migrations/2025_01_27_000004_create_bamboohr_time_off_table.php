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
        Schema::create('bamboohr_time_off', function (Blueprint $table) {
            $table->id();
            $table->string('bamboohr_id')->unique();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('type');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('days_requested', 5, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('requested_date')->nullable();
            $table->timestamp('approved_date')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('sync_status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('bamboohr_employees')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('bamboohr_employees')->onDelete('set null');
            $table->index(['bamboohr_id', 'sync_status']);
            $table->index(['employee_id']);
            $table->index(['status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bamboohr_time_off');
    }
};