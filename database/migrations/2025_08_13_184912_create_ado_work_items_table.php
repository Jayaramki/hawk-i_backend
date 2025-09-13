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
        Schema::create('ado_work_items', function (Blueprint $table) {
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
            $table->string('title', 500)->nullable();
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
            $table->string('assigned_to_display_name', 255)->nullable();
            $table->string('created_by')->nullable();
            $table->string('created_by_display_name')->nullable();
            $table->string('modified_by')->nullable();
            $table->string('modified_by_display_name', 255)->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamp('changed_date')->nullable();
            $table->timestamp('closed_date')->nullable();
            $table->timestamp('resolved_date')->nullable();
            $table->text('area_path')->nullable();
            $table->json('tags')->nullable();
            $table->string('parent_id')->nullable();
            $table->string('parent_title')->nullable();
            $table->integer('children_count')->default(0);
            $table->integer('revision')->default(1);
            
            // Custom fields
            $table->string('ruddr_task_name')->nullable()->comment('Custom.RuddrTaskName from Azure DevOps');
            $table->string('ruddr_project_id')->nullable()->comment('Custom.RuddrProjectUID from Azure DevOps');
            $table->datetime('task_start_dt')->nullable()->comment('Custom.StartDt from Azure DevOps');
            $table->datetime('task_end_dt')->nullable()->comment('Custom.EndDt from Azure DevOps');
            $table->datetime('delayed_completion')->nullable()->comment('Custom.DelayedCompletion from Azure DevOps');
            $table->string('delayed_reason', 255)->nullable()->comment('Custom.DelayedReason from Azure DevOps');
            $table->string('moved_from_sprint', 50)->nullable()->comment('Custom.MovedFromSprint from Azure DevOps');
            $table->string('spillover_reason', 50)->nullable()->comment('Custom.SpilloverReason from Azure DevOps');
            $table->decimal('effort_saved_using_ai', 8, 2)->nullable()->comment('Custom.EffortSavedUsingAI from Azure DevOps');
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('project_id')->references('id')->on('ado_projects')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('ado_teams')->onDelete('set null');
            $table->foreign('iteration_id')->references('id')->on('ado_iterations')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('ado_users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('ado_users')->onDelete('set null');
            $table->foreign('modified_by')->references('id')->on('ado_users')->onDelete('set null');
            
            // Indexes for better performance
            $table->index(['project_id']);
            $table->index(['team_id']);
            $table->index(['iteration_id']);
            $table->index(['work_item_type']);
            $table->index(['state']);
            $table->index(['assigned_to']);
            $table->index(['created_by']);
            $table->index(['created_date']);
            $table->index(['changed_date']);
            $table->index(['priority']);
            $table->index(['work_item_type', 'state']);
            
            // Indexes for custom fields
            $table->index(['ruddr_task_name']);
            $table->index(['ruddr_project_id']);
            $table->index(['task_start_dt']);
            $table->index(['task_end_dt']);
            $table->index(['delayed_completion']);
            $table->index(['moved_from_sprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ado_work_items');
    }
};