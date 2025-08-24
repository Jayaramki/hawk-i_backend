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
            // Custom date/time fields
            $table->datetime('task_start_dt')->nullable()->after('ruddr_project_id')->comment('Custom.StartDt from Azure DevOps');
            $table->datetime('task_end_dt')->nullable()->after('task_start_dt')->comment('Custom.EndDt from Azure DevOps');
            $table->datetime('delayed_completion')->nullable()->after('task_end_dt')->comment('Custom.DelayedCompletion from Azure DevOps');
            
            // Custom string fields
            $table->string('delayed_reason', 255)->nullable()->after('delayed_completion')->comment('Custom.DelayedReason from Azure DevOps');
            $table->string('moved_from_sprint', 50)->nullable()->after('delayed_reason')->comment('Custom.MovedFromSprint from Azure DevOps');
            $table->string('spillover_reason', 50)->nullable()->after('moved_from_sprint')->comment('Custom.SpilloverReason from Azure DevOps');
            
            // Custom numeric field
            $table->decimal('effort_saved_using_ai', 8, 2)->nullable()->after('spillover_reason')->comment('Custom.EffortSavedUsingAI from Azure DevOps');
            
            // Add indexes for commonly queried fields
            $table->index(['task_start_dt']);
            $table->index(['task_end_dt']);
            $table->index(['delayed_completion']);
            $table->index(['moved_from_sprint']);
            
            // Remove the fields column as we now have specific columns for the data we need
            $table->dropColumn('fields');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ado_work_items', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['task_start_dt']);
            $table->dropIndex(['task_end_dt']);
            $table->dropIndex(['delayed_completion']);
            $table->dropIndex(['moved_from_sprint']);
            
            // Drop the new columns
            $table->dropColumn([
                'task_start_dt',
                'task_end_dt',
                'delayed_completion',
                'delayed_reason',
                'moved_from_sprint',
                'spillover_reason',
                'effort_saved_using_ai'
            ]);
            
            // Re-add the fields column that we're removing
            $table->json('fields')->nullable()->comment('Full fields JSON from Azure DevOps API');
        });
    }
};
