<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_client_request_item_services', function (Blueprint $table): void {
            $table->uuid('request_item_id');
            $table->uuid('service_requirement_id');
            $table->timestamps();
            $table->primary(['request_item_id', 'service_requirement_id']);
            $table->foreign('request_item_id')->references('id')->on('referral_client_request_items')->cascadeOnDelete();
            $table->foreign('service_requirement_id')->references('id')->on('referral_service_requirements')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_client_request_item_services');
    }
};
