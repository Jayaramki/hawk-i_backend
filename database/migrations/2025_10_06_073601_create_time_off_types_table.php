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
        Schema::create('time_off_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('bamboohr_id')->nullable(); // BambooHR type ID
            $table->string('icon')->nullable(); // BambooHR icon
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_off_types');
    }
};