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
            $table->id();
            $table->string('team_id');
            $table->unsignedBigInteger('iteration_id');
            $table->string('project_id');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            
            $table->foreign('team_id')->references('id')->on('ado_teams')->onDelete('cascade');
            $table->foreign('iteration_id')->references('id')->on('ado_iterations')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('ado_projects')->onDelete('cascade');
            $table->unique(['team_id', 'iteration_id']);
            $table->index(['team_id']);
            $table->index(['iteration_id']);
            $table->index(['project_id']);
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
