<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {
            $table->dropColumn(['line1', 'line2', 'city', 'postal_code', 'country']);
        });

        Schema::table('client_addresses', function (Blueprint $table) {
            $table->string('region')->nullable()->after('client_id');
            $table->string('city_municipality')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('city_municipality');
            $table->text('street')->nullable()->after('barangay');
        });
    }

    public function down(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {
            $table->dropColumn(['region', 'city_municipality', 'barangay', 'street']);
        });

        Schema::table('client_addresses', function (Blueprint $table) {
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
        });
    }
};
