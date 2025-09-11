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
        Schema::dropIfExists('ado_users_new');
        
        // Step 1: Create new table with correct structure
        Schema::create('ado_users_new', function (Blueprint $table) {
            $table->string('id')->primary(); // Use Azure DevOps descriptor as primary key
            $table->string('display_name');
            $table->string('mail_address')->nullable();
            $table->string('origin');
            $table->string('origin_id')->nullable();
            $table->string('subject_kind');
            $table->text('url');
            $table->string('meta_type')->nullable();
            $table->string('directory_alias')->nullable();
            $table->string('domain')->nullable();
            $table->string('principal_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['subject_kind']);
            $table->index(['origin']);
            $table->index(['is_active']);
        });

        // Step 2: Copy data from old table to new table
        if (Schema::hasTable('ado_users')) {
            \DB::statement('INSERT INTO ado_users_new (id, display_name, mail_address, origin, origin_id, subject_kind, url, meta_type, directory_alias, domain, principal_name, is_active, created_at, updated_at) 
                           SELECT descriptor, display_name, mail_address, origin, origin_id, subject_kind, url, meta_type, directory_alias, domain, principal_name, is_active, created_at, updated_at 
                           FROM ado_users');
        }

        // Step 3: Drop foreign key constraints that reference ado_users
        try {
            \DB::statement('ALTER TABLE ado_work_items DROP FOREIGN KEY ado_work_items_assigned_to_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist
        }
        try {
            \DB::statement('ALTER TABLE ado_work_items DROP FOREIGN KEY ado_work_items_created_by_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist
        }
        try {
            \DB::statement('ALTER TABLE ado_work_items DROP FOREIGN KEY ado_work_items_modified_by_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist
        }

        // Step 4: Drop old table and rename new table
        Schema::dropIfExists('ado_users');
        Schema::rename('ado_users_new', 'ado_users');
        
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
        Schema::create('ado_users_old', function (Blueprint $table) {
            $table->id();
            $table->string('descriptor')->unique();
            $table->string('display_name');
            $table->string('mail_address')->nullable();
            $table->string('origin');
            $table->string('origin_id')->nullable();
            $table->string('subject_kind');
            $table->text('url');
            $table->string('meta_type')->nullable();
            $table->string('directory_alias')->nullable();
            $table->string('domain')->nullable();
            $table->string('principal_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['subject_kind']);
            $table->index(['origin']);
            $table->index(['is_active']);
        });

        // Step 2: Copy data back
        if (Schema::hasTable('ado_users')) {
            \DB::statement('INSERT INTO ado_users_old (descriptor, display_name, mail_address, origin, origin_id, subject_kind, url, meta_type, directory_alias, domain, principal_name, is_active, created_at, updated_at) 
                           SELECT id, display_name, mail_address, origin, origin_id, subject_kind, url, meta_type, directory_alias, domain, principal_name, is_active, created_at, updated_at 
                           FROM ado_users');
        }

        // Step 3: Drop new table and rename old table back
        Schema::dropIfExists('ado_users');
        Schema::rename('ado_users_old', 'ado_users');
        
        // Re-enable foreign key checks
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
