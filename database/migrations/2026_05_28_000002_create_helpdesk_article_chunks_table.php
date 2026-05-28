<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_article_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('article_id');
            $table->longText('content');
            $table->integer('chunk_index');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('helpdesk_article_chunks', function (Blueprint $table) {
            $table->foreign('article_id')->references('id')->on('helpdesk_articles')->onDelete('cascade');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_article_chunks');
    }
};
