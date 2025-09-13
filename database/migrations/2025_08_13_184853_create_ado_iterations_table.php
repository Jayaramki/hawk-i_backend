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
        Schema::create('ado_iterations', function (Blueprint $table) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ado_iterations');
    }
};