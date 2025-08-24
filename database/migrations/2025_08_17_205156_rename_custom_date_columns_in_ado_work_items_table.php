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
            // Rename custom date columns to more descriptive names
            $table->renameColumn('custom_start_date', 'task_start_dt');
            $table->renameColumn('custom_end_date', 'task_end_dt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ado_work_items', function (Blueprint $table) {
            // Rename back to original names
            $table->renameColumn('task_start_dt', 'custom_start_date');
            $table->renameColumn('task_end_dt', 'custom_end_date');
        });
    }
};
