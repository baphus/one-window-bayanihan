<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['employment_last_country', 'employment_start_date', 'employment_end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->text('employment_last_country')->nullable();
            $table->date('employment_start_date')->nullable();
            $table->date('employment_end_date')->nullable();
        });
    }
};
