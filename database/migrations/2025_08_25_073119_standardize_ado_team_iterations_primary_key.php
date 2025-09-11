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
        // Disable foreign key checks temporarily
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Clean up any existing temporary table
        Schema::dropIfExists('ado_team_iterations_new');
        
        // Step 1: Create new table with correct structure
        Schema::create('ado_team_iterations_new', function (Blueprint $table) {
            $table->string('id')->primary(); // Generate composite ID from team_id + iteration_id
            $table->string('iteration_identifier');
            $table->string('team_id');
            $table->string('team_name');
            $table->string('timeframe')->nullable();
            $table->boolean('assigned')->default(false);
            $table->string('iteration_name');
            $table->text('iteration_path');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('project_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('team_id')->references('id')->on('ado_teams')->onDelete('cascade');
            $table->foreign('iteration_identifier')->references('id')->on('ado_iterations')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('ado_projects')->onDelete('cascade');
            $table->index(['team_id']);
            $table->index(['iteration_identifier']);
            $table->index(['project_id']);
            $table->index(['is_active']);
        });

        // Step 2: Copy data from old table to new table, generating composite IDs
        if (Schema::hasTable('ado_team_iterations')) {
            \DB::statement('INSERT INTO ado_team_iterations_new (id, iteration_identifier, team_id, team_name, timeframe, assigned, iteration_name, iteration_path, start_date, end_date, project_id, is_active, created_at, updated_at) 
                           SELECT CONCAT(team_id, "-", iteration_identifier), iteration_identifier, team_id, team_name, timeframe, assigned, iteration_name, iteration_path, start_date, end_date, project_id, is_active, created_at, updated_at 
                           FROM ado_team_iterations');
        }

        // Step 3: Drop old table and rename new table
        Schema::dropIfExists('ado_team_iterations');
        Schema::rename('ado_team_iterations_new', 'ado_team_iterations');
        
        // Re-enable foreign key checks
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks temporarily
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Step 1: Create old table structure
        Schema::create('ado_team_iterations_old', function (Blueprint $table) {
            $table->id();
            $table->string('iteration_identifier');
            $table->string('team_id');
            $table->string('team_name');
            $table->string('timeframe')->nullable();
            $table->boolean('assigned')->default(false);
            $table->string('iteration_name');
            $table->text('iteration_path');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('project_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('team_id')->references('id')->on('ado_teams')->onDelete('cascade');
            $table->foreign('iteration_identifier')->references('id')->on('ado_iterations')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('ado_projects')->onDelete('cascade');
            $table->unique(['team_id', 'iteration_identifier']);
            $table->index(['team_id']);
            $table->index(['iteration_identifier']);
            $table->index(['project_id']);
            $table->index(['is_active']);
        });

        // Step 2: Copy data back
        if (Schema::hasTable('ado_team_iterations')) {
            \DB::statement('INSERT INTO ado_team_iterations_old (iteration_identifier, team_id, team_name, timeframe, assigned, iteration_name, iteration_path, start_date, end_date, project_id, is_active, created_at, updated_at) 
                           SELECT iteration_identifier, team_id, team_name, timeframe, assigned, iteration_name, iteration_path, start_date, end_date, project_id, is_active, created_at, updated_at 
                           FROM ado_team_iterations');
        }

        // Step 3: Drop new table and rename old table back
        Schema::dropIfExists('ado_team_iterations');
        Schema::rename('ado_team_iterations_old', 'ado_team_iterations');
        
        // Re-enable foreign key checks
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
