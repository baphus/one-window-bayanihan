<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_services', function (Blueprint $table) {
            $table->uuid('referral_id');
            $table->uuid('service_id');
            $table->timestamps();

            $table->primary(['referral_id', 'service_id']);

            $table->foreign('referral_id')
                ->references('id')
                ->on('referrals')
                ->onDelete('cascade');

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_services');
    }
};
