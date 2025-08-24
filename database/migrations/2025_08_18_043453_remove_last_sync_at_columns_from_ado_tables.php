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
        // Remove last_sync_at columns from all ADO tables
        Schema::table('ado_projects', function (Blueprint $table) {
            $table->dropColumn('last_sync_at');
        });

        Schema::table('ado_users', function (Blueprint $table) {
            $table->dropColumn('last_sync_at');
        });

        Schema::table('ado_teams', function (Blueprint $table) {
            $table->dropColumn('last_sync_at');
        });

        Schema::table('ado_iterations', function (Blueprint $table) {
            $table->dropColumn('last_sync_at');
        });

        Schema::table('ado_team_iterations', function (Blueprint $table) {
            $table->dropColumn('last_sync_at');
        });

        Schema::table('ado_work_items', function (Blueprint $table) {
            $table->dropColumn('last_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add last_sync_at columns if migration is rolled back
        Schema::table('ado_projects', function (Blueprint $table) {
            $table->timestamp('last_sync_at')->nullable()->comment('When this project was last synced');
        });

        Schema::table('ado_users', function (Blueprint $table) {
            $table->timestamp('last_sync_at')->nullable()->comment('When this user was last synced');
        });

        Schema::table('ado_teams', function (Blueprint $table) {
            $table->timestamp('last_sync_at')->nullable()->comment('When this team was last synced');
        });

        Schema::table('ado_iterations', function (Blueprint $table) {
            $table->timestamp('last_sync_at')->nullable()->comment('When this iteration was last synced');
        });

        Schema::table('ado_team_iterations', function (Blueprint $table) {
            $table->timestamp('last_sync_at')->nullable()->comment('When this team iteration was last synced');
        });

        Schema::table('ado_work_items', function (Blueprint $table) {
            $table->timestamp('last_sync_at')->nullable()->comment('When this work item was last synced');
        });
    }
};
