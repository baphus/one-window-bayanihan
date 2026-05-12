<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required');
            $table->uuid('service_id');
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requirements');
    }
};
