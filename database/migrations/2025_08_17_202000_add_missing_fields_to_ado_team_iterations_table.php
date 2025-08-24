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
            // Only add columns that don't exist yet (others already exist from previous migrations)
            if (!Schema::hasColumn('ado_team_iterations', 'iteration_name')) {
                $table->string('iteration_name')->nullable()->after('assigned')->comment('Iteration name from Azure DevOps');
            }
            if (!Schema::hasColumn('ado_team_iterations', 'iteration_path')) {
                $table->string('iteration_path')->nullable()->after('iteration_name')->comment('Iteration path from Azure DevOps');
            }
            if (!Schema::hasColumn('ado_team_iterations', 'start_date')) {
                $table->date('start_date')->nullable()->after('iteration_path')->comment('Iteration start date from attributes');
            }
            if (!Schema::hasColumn('ado_team_iterations', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date')->comment('Iteration end date (finishDate) from attributes');
            }
            
            // Add indexes for new fields (after they're created)
            if (Schema::hasColumn('ado_team_iterations', 'start_date')) {
                $table->index(['start_date']);
            }
            if (Schema::hasColumn('ado_team_iterations', 'end_date')) {
                $table->index(['end_date']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ado_team_iterations', function (Blueprint $table) {
            // Only drop columns and indexes that this migration added
            if (Schema::hasColumn('ado_team_iterations', 'start_date')) {
                $table->dropIndex(['start_date']);
            }
            if (Schema::hasColumn('ado_team_iterations', 'end_date')) {
                $table->dropIndex(['end_date']);
            }
            
            // Drop only the columns this migration added
            $columnsToCheck = ['iteration_name', 'iteration_path', 'start_date', 'end_date'];
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('ado_team_iterations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
