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
            $table->string('ruddr_task_name')->nullable()->after('tags')->comment('Custom.RuddrTaskName from Azure DevOps');
            $table->string('ruddr_project_id')->nullable()->after('ruddr_task_name')->comment('Custom.RuddrProjectUID from Azure DevOps');
            
            // Add indexes for better performance on custom fields
            $table->index(['ruddr_task_name']);
            $table->index(['ruddr_project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ado_work_items', function (Blueprint $table) {
            $table->dropIndex(['ruddr_task_name']);
            $table->dropIndex(['ruddr_project_id']);
            $table->dropColumn(['ruddr_task_name', 'ruddr_project_id']);
        });
    }
};
