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
        Schema::table('bamboohr_employees', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('last_name');
            $table->string('supervisor_e_id')->nullable()->after('supervisor_id');
            $table->string('employment_status')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bamboohr_employees', function (Blueprint $table) {
            $table->dropColumn(['gender', 'supervisor_e_id', 'employment_status']);
        });
    }
};
