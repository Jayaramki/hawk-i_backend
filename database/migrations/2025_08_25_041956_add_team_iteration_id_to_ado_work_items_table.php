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
        Schema::table('ado_work_items', function (Blueprint $table) {
            // Add team_iteration_id field to link work items to team iterations
            $table->unsignedBigInteger('team_iteration_id')->nullable()->after('iteration_id');
            
            // Add foreign key constraint
            $table->foreign('team_iteration_id')->references('id')->on('ado_team_iterations')->onDelete('set null');
            
            // Add index for performance
            $table->index(['team_iteration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ado_work_items', function (Blueprint $table) {
            // Drop foreign key and index
            $table->dropForeign(['team_iteration_id']);
            $table->dropIndex(['team_iteration_id']);
            
            // Drop column
            $table->dropColumn('team_iteration_id');
        });
    }
};
