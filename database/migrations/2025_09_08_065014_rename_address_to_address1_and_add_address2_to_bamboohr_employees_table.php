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
        Schema::table('bamboohr_employees', function (Blueprint $table) {
            $table->renameColumn('address', 'address1');
            $table->text('address2')->nullable()->after('address1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bamboohr_employees', function (Blueprint $table) {
            $table->dropColumn('address2');
            $table->renameColumn('address1', 'address');
        });
    }
};
