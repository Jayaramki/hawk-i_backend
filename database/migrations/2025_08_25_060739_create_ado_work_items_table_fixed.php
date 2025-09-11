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
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('parent_title')->nullable();
            $table->integer('children_count')->default(0);
            $table->integer('revision')->default(1);
            $table->json('fields')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('project_id')->references('id')->on('ado_projects')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('ado_teams')->onDelete('set null');
            $table->foreign('iteration_id')->references('id')->on('ado_iterations')->onDelete('set null');
            $table->foreign('team_iteration_id')->references('id')->on('ado_team_iterations')->onDelete('set null');
            $table->foreign('assigned_to')->references('descriptor')->on('ado_users')->onDelete('set null');
            $table->foreign('created_by')->references('descriptor')->on('ado_users')->onDelete('set null');
            $table->foreign('modified_by')->references('descriptor')->on('ado_users')->onDelete('set null');
            
            // Indexes
            $table->index(['project_id']);
            $table->index(['team_id']);
            $table->index(['iteration_id']);
            $table->index(['team_iteration_id']);
            $table->index(['work_item_type']);
            $table->index(['state']);
            $table->index(['assigned_to']);
            $table->index(['created_by']);
            $table->index(['created_date']);
            $table->index(['changed_date']);
            $table->index(['ado_work_item_id']);
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
