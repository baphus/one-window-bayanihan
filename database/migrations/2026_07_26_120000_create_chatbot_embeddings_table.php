<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->string('source_key');
            $table->string('slug');
            $table->string('heading');
            $table->string('audience_group');
            $table->string('content_hash');
            $table->vector('embedding', 768)->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_key']);
            $table->index('source_type');
            $table->index('slug');
            $table->index('audience_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_embeddings');
    }
};
