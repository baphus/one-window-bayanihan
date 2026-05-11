<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('system_settings')->insert([
            'key' => 'debug_otp_enabled',
            'value' => 'false',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
