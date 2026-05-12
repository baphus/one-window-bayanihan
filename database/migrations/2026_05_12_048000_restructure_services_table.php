<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('agency_service');

        Schema::table('services', function (Blueprint $table) {
            $table->uuid('agcy_id');
            $table->integer('processing_days')->nullable();

            $table->foreign('agcy_id')->references('id')->on('agencies')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['agcy_id']);
            $table->dropColumn(['agcy_id', 'processing_days']);
        });

        Schema::create('agency_service', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id');
            $table->uuid('service_id');
            $table->text('required_documents')->nullable();
            $table->integer('processing_days')->nullable();
            $table->timestamps();

            $table->foreign('agency_id')->references('id')->on('agencies')->onDelete('restrict');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');
            $table->unique(['agency_id', 'service_id']);
        });
    }
};
