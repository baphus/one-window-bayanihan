<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_employments', function (Blueprint $table) {
            $table->string('last_country')->nullable()->after('country');
            $table->string('last_position')->nullable()->after('position');
            $table->date('date_of_arrival')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('client_employments', function (Blueprint $table) {
            $table->dropColumn(['last_country', 'last_position', 'date_of_arrival']);
        });
    }
};
