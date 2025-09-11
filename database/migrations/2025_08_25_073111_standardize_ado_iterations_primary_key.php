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
        Schema::dropIfExists('ado_iterations_new');
        
        // Step 1: Create new table with correct structure
        Schema::create('ado_iterations_new', function (Blueprint $table) {
            $table->string('id')->primary(); // Use Azure DevOps identifier as primary key
            $table->string('name');
            $table->text('path');
            $table->text('url')->nullable();
            $table->string('project_id');
            $table->string('project_name');
            $table->date('start_date')->nullable();
            $table->date('finish_date')->nullable();
            $table->string('time_frame')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('project_id')->references('id')->on('ado_projects')->onDelete('cascade');
            $table->index(['project_id']);
            $table->index(['start_date', 'finish_date']);
            $table->index(['is_active']);
        });

        // Step 2: Copy data from old table to new table
        if (Schema::hasTable('ado_iterations')) {
            \DB::statement('INSERT INTO ado_iterations_new (id, name, path, url, project_id, project_name, start_date, finish_date, time_frame, attributes, is_active, created_at, updated_at) 
                           SELECT identifier, name, path, url, project_id, project_name, start_date, finish_date, time_frame, attributes, is_active, created_at, updated_at 
                           FROM ado_iterations');
        }

        // Step 3: Drop old table and rename new table
        Schema::dropIfExists('ado_iterations');
        Schema::rename('ado_iterations_new', 'ado_iterations');
        
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
        Schema::create('ado_iterations_old', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('name');
            $table->text('path');
            $table->text('url')->nullable();
            $table->string('project_id');
            $table->string('project_name');
            $table->date('start_date')->nullable();
            $table->date('finish_date')->nullable();
            $table->string('time_frame')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('project_id')->references('id')->on('ado_projects')->onDelete('cascade');
            $table->index(['project_id']);
            $table->index(['identifier']);
            $table->index(['start_date', 'finish_date']);
            $table->index(['is_active']);
        });

        // Step 2: Copy data back
        if (Schema::hasTable('ado_iterations')) {
            \DB::statement('INSERT INTO ado_iterations_old (identifier, name, path, url, project_id, project_name, start_date, finish_date, time_frame, attributes, is_active, created_at, updated_at) 
                           SELECT id, name, path, url, project_id, project_name, start_date, finish_date, time_frame, attributes, is_active, created_at, updated_at 
                           FROM ado_iterations');
        }

        // Step 3: Drop new table and rename old table back
        Schema::dropIfExists('ado_iterations');
        Schema::rename('ado_iterations_old', 'ado_iterations');
        
        // Re-enable foreign key checks
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
