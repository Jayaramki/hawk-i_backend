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
        Schema::create('sync_history', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 50)->comment('Name of the table being synced (projects, teams, iterations, etc.)');
            $table->string('project_id', 50)->nullable()->comment('Project ID for project-specific syncs (null for global syncs like projects)');
            $table->timestamp('last_sync_at')->comment('When the last successful sync occurred');
            $table->enum('sync_type', ['full', 'incremental'])->default('full')->comment('Type of sync performed');
            $table->enum('status', ['success', 'failed', 'in_progress'])->default('success')->comment('Status of the sync');
            $table->integer('records_processed')->default(0)->comment('Number of records processed in the sync');
            $table->text('error_message')->nullable()->comment('Error message if sync failed');
            $table->timestamps();
            
            // Unique constraint to ensure one record per table per project
            $table->unique(['table_name', 'project_id'], 'sync_history_table_project_unique');
            
            // Indexes for performance
            $table->index(['table_name']);
            $table->index(['project_id']);
            $table->index(['last_sync_at']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_history');
    }
};
