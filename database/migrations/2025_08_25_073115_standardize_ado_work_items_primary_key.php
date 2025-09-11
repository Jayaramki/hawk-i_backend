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
        Schema::dropIfExists('ado_work_items_new');
        
        // Step 1: Create new table with correct structure
        Schema::create('ado_work_items_new', function (Blueprint $table) {
            $table->string('id')->primary(); // Use Azure DevOps work item ID as primary key
            $table->text('url');
            $table->string('project_id');
            $table->string('project_name');
            $table->string('team_id')->nullable();
            $table->string('team_name')->nullable();
            $table->string('iteration_id')->nullable();
            $table->string('team_iteration_id')->nullable();
            $table->text('iteration_path')->nullable();
            $table->string('work_item_type')->nullable();
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->string('state')->nullable();
            $table->integer('priority')->nullable();
            $table->integer('severity')->nullable();
            $table->decimal('story_points', 8, 2)->nullable();
            $table->decimal('effort', 8, 2)->nullable();
            $table->decimal('remaining_work', 8, 2)->nullable();
            $table->decimal('completed_work', 8, 2)->nullable();
            $table->decimal('original_estimate', 8, 2)->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('assigned_to_display_name')->nullable();
            $table->string('created_by')->nullable();
            $table->string('created_by_display_name')->nullable();
            $table->string('modified_by')->nullable();
            $table->string('modified_by_display_name')->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamp('changed_date')->nullable();
            $table->timestamp('closed_date')->nullable();
            $table->timestamp('resolved_date')->nullable();
            $table->text('area_path')->nullable();
            $table->json('tags')->nullable();
            $table->string('ruddr_task_name')->nullable();
            $table->string('ruddr_project_id')->nullable();
            $table->timestamp('task_start_dt')->nullable();
            $table->timestamp('task_end_dt')->nullable();
            $table->timestamp('delayed_completion')->nullable();
            $table->text('delayed_reason')->nullable();
            $table->text('moved_from_sprint')->nullable();
            $table->text('spillover_reason')->nullable();
            $table->decimal('effort_saved_using_ai', 8, 2)->nullable();
            $table->string('parent_id')->nullable();
            $table->timestamps();
            
            $table->foreign('project_id')->references('id')->on('ado_projects')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('ado_teams')->onDelete('set null');
            $table->foreign('iteration_id')->references('id')->on('ado_iterations')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('ado_users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('ado_users')->onDelete('set null');
            $table->foreign('modified_by')->references('id')->on('ado_users')->onDelete('set null');
            
            $table->index(['project_id']);
            $table->index(['team_id']);
            $table->index(['iteration_id']);
            $table->index(['work_item_type']);
            $table->index(['state']);
            $table->index(['assigned_to']);
        });

        // Step 2: Copy data from old table to new table
        if (Schema::hasTable('ado_work_items')) {
            \DB::statement('INSERT INTO ado_work_items_new (id, url, project_id, project_name, team_id, team_name, iteration_id, team_iteration_id, iteration_path, work_item_type, title, description, state, priority, severity, story_points, effort, remaining_work, completed_work, original_estimate, assigned_to, assigned_to_display_name, created_by, created_by_display_name, modified_by, modified_by_display_name, created_date, changed_date, closed_date, resolved_date, area_path, tags, ruddr_task_name, ruddr_project_id, task_start_dt, task_end_dt, delayed_completion, delayed_reason, moved_from_sprint, spillover_reason, effort_saved_using_ai, parent_id, created_at, updated_at) 
                           SELECT CAST(ado_work_item_id AS CHAR), url, project_id, project_name, team_id, team_name, CAST(iteration_id AS CHAR), CAST(team_iteration_id AS CHAR), iteration_path, work_item_type, title, description, state, priority, severity, story_points, effort, remaining_work, completed_work, original_estimate, assigned_to, assigned_to_display_name, created_by, created_by_display_name, modified_by, modified_by_display_name, created_date, changed_date, closed_date, resolved_date, area_path, tags, ruddr_task_name, ruddr_project_id, task_start_dt, task_end_dt, delayed_completion, delayed_reason, moved_from_sprint, spillover_reason, effort_saved_using_ai, CAST(parent_id AS CHAR), created_at, updated_at 
                           FROM ado_work_items');
        }

        // Step 3: Drop old table and rename new table
        Schema::dropIfExists('ado_work_items');
        Schema::rename('ado_work_items_new', 'ado_work_items');
        
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
        Schema::create('ado_work_items_old', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->unsignedBigInteger('ado_work_item_id')->unique(); // Azure DevOps work item ID
            $table->text('url');
            $table->string('project_id');
            $table->string('project_name');
            $table->string('team_id')->nullable();
            $table->string('team_name')->nullable();
            $table->unsignedBigInteger('iteration_id')->nullable();
            $table->unsignedBigInteger('team_iteration_id')->nullable();
            $table->text('iteration_path')->nullable();
            $table->string('work_item_type')->nullable();
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->string('state')->nullable();
            $table->integer('priority')->nullable();
            $table->integer('severity')->nullable();
            $table->decimal('story_points', 8, 2)->nullable();
            $table->decimal('effort', 8, 2)->nullable();
            $table->decimal('remaining_work', 8, 2)->nullable();
            $table->decimal('completed_work', 8, 2)->nullable();
            $table->decimal('original_estimate', 8, 2)->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('assigned_to_display_name')->nullable();
            $table->string('created_by')->nullable();
            $table->string('created_by_display_name')->nullable();
            $table->string('modified_by')->nullable();
            $table->string('modified_by_display_name')->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamp('changed_date')->nullable();
            $table->timestamp('closed_date')->nullable();
            $table->timestamp('resolved_date')->nullable();
            $table->text('area_path')->nullable();
            $table->json('tags')->nullable();
            $table->string('ruddr_task_name')->nullable();
            $table->string('ruddr_project_id')->nullable();
            $table->timestamp('task_start_dt')->nullable();
            $table->timestamp('task_end_dt')->nullable();
            $table->timestamp('delayed_completion')->nullable();
            $table->text('delayed_reason')->nullable();
            $table->text('moved_from_sprint')->nullable();
            $table->text('spillover_reason')->nullable();
            $table->decimal('effort_saved_using_ai', 8, 2)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            
            $table->foreign('project_id')->references('id')->on('ado_projects')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('ado_teams')->onDelete('set null');
            $table->foreign('iteration_id')->references('id')->on('ado_iterations')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('ado_users')->onDelete('set null');
            
            $table->index(['project_id']);
            $table->index(['team_id']);
            $table->index(['iteration_id']);
            $table->index(['work_item_type']);
            $table->index(['state']);
            $table->index(['assigned_to']);
        });

        // Step 2: Copy data back (this is complex due to data type changes)
        if (Schema::hasTable('ado_work_items')) {
            \DB::statement('INSERT INTO ado_work_items_old (ado_work_item_id, url, project_id, project_name, team_id, team_name, iteration_id, team_iteration_id, iteration_path, work_item_type, title, description, state, priority, severity, story_points, effort, remaining_work, completed_work, original_estimate, assigned_to, assigned_to_display_name, created_by, created_by_display_name, modified_by, modified_by_display_name, created_date, changed_date, closed_date, resolved_date, area_path, tags, ruddr_task_name, ruddr_project_id, task_start_dt, task_end_dt, delayed_completion, delayed_reason, moved_from_sprint, spillover_reason, effort_saved_using_ai, parent_id, created_at, updated_at) 
                           SELECT CAST(id AS UNSIGNED), url, project_id, project_name, team_id, team_name, CAST(iteration_id AS UNSIGNED), CAST(team_iteration_id AS UNSIGNED), iteration_path, work_item_type, title, description, state, priority, severity, story_points, effort, remaining_work, completed_work, original_estimate, assigned_to, assigned_to_display_name, created_by, created_by_display_name, modified_by, modified_by_display_name, created_date, changed_date, closed_date, resolved_date, area_path, tags, ruddr_task_name, ruddr_project_id, task_start_dt, task_end_dt, delayed_completion, delayed_reason, moved_from_sprint, spillover_reason, effort_saved_using_ai, CAST(parent_id AS UNSIGNED), created_at, updated_at 
                           FROM ado_work_items WHERE id REGEXP "^[0-9]+$"');
        }

        // Step 3: Drop new table and rename old table back
        Schema::dropIfExists('ado_work_items');
        Schema::rename('ado_work_items_old', 'ado_work_items');
        
        // Re-enable foreign key checks
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
