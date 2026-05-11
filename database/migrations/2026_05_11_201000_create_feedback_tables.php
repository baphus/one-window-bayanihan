<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->uuid('agency_id')->nullable();
            $table->string('service_name')->nullable();
            $table->integer('overall_rating')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('restrict');
            $table->foreign('agency_id')->references('id')->on('agencies')->onDelete('restrict');
        });

        Schema::create('feedback_servqual_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('feedback_id');
            $table->string('question_id');
            $table->text('question_text');
            $table->string('dimension');
            $table->integer('expectation')->nullable();
            $table->integer('perception')->nullable();
            $table->timestamps();

            $table->foreign('feedback_id')->references('id')->on('feedback')->onDelete('cascade');
        });

        Schema::create('servqual_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id');
            $table->string('service_name');
            $table->json('questions');
            $table->timestamps();

            $table->foreign('agency_id')->references('id')->on('agencies')->onDelete('restrict');
            $table->unique(['agency_id', 'service_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servqual_configs');
        Schema::dropIfExists('feedback_servqual_responses');
        Schema::dropIfExists('feedback');
    }
};
