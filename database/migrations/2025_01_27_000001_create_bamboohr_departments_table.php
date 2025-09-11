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
        Schema::create('bamboohr_departments', function (Blueprint $table) {
            $table->id();
            $table->string('bamboohr_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_department_id')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('sync_status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('parent_department_id')->references('id')->on('bamboohr_departments')->onDelete('set null');
            $table->index(['bamboohr_id', 'sync_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bamboohr_departments');
    }
};
