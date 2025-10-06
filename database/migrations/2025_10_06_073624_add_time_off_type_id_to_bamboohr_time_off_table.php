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
        Schema::table('bamboohr_time_off', function (Blueprint $table) {
            $table->unsignedBigInteger('time_off_type_id')->nullable()->after('type');
            $table->foreign('time_off_type_id')->references('id')->on('time_off_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bamboohr_time_off', function (Blueprint $table) {
            $table->dropForeign(['time_off_type_id']);
            $table->dropColumn('time_off_type_id');
        });
    }
};