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
        Schema::create('ado_projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('url');
            $table->string('state');
            $table->integer('revision');
            $table->string('visibility');
            $table->string('default_team_id')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            
            $table->index(['state']);
            $table->index(['visibility']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ado_projects');
    }
};
