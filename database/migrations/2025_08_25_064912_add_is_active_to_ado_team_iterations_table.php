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
        Schema::table('ado_team_iterations', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ado_team_iterations', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
