<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('helpdesk_article_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('article_slug', 160);
            $table->string('identity_hash', 64);
            $table->string('vote', 3);
            $table->timestamps();
            $table->unique(['article_slug', 'identity_hash']);
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helpdesk_article_feedback');
    }
};
