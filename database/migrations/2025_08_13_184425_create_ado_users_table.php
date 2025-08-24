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
        Schema::create('ado_users', function (Blueprint $table) {
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
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            
            $table->index(['subject_kind']);
            $table->index(['origin']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ado_users');
    }
};
