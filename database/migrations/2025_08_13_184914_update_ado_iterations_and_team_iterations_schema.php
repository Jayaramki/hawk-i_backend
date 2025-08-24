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
        // Update ado_team_iterations table
        Schema::table('ado_team_iterations', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['iteration_id']);
            
            // Drop the unique constraint and index that reference iteration_id
            $table->dropUnique('ado_team_iterations_team_id_iteration_id_unique');
            $table->dropIndex('ado_team_iterations_iteration_id_index');
            
            // Drop the existing iteration_id column
            $table->dropColumn('iteration_id');
            
            // Add new columns to match Python script structure
            $table->string('iteration_identifier')->after('team_id');
            $table->string('team_name')->after('team_id');
            $table->string('timeframe')->nullable()->after('team_name');
            $table->boolean('assigned')->default(true)->after('timeframe');
            
            // Add index for iteration_identifier
            $table->index(['iteration_identifier']);
            
            // Update unique constraint
            $table->unique(['team_id', 'iteration_identifier'], 'team_iteration_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert ado_team_iterations table
        Schema::table('ado_team_iterations', function (Blueprint $table) {
            // Drop new columns
            $table->dropIndex(['iteration_identifier']);
            $table->dropUnique('team_iteration_unique');
            $table->dropColumn(['iteration_identifier', 'team_name', 'timeframe', 'assigned']);
            
            // Add back the original iteration_id column
            $table->unsignedBigInteger('iteration_id')->after('team_id');
            $table->foreign('iteration_id')->references('id')->on('ado_iterations')->onDelete('cascade');
            $table->unique(['team_id', 'iteration_id']);
            $table->index(['iteration_id']);
        });
    }
};
