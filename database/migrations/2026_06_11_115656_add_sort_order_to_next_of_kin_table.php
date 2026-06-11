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
        Schema::table('next_of_kin', function (Blueprint $table) {
            $table->integer('sort_order')->nullable()->default(0)->after('street');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('next_of_kin', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
