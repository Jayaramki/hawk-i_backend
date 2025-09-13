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
        Schema::create('ado_team_iterations', function (Blueprint $table) {
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
            $table->index(['start_date']);
            $table->index(['end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ado_team_iterations');
    }
};