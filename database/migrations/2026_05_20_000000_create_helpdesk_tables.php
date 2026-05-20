<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
        });

        Schema::table('helpdesk_categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('helpdesk_categories')->onDelete('restrict');
        });

        Schema::create('helpdesk_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
        });

        Schema::create('helpdesk_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content_markdown');
            $table->text('excerpt')->nullable();
            $table->uuid('category_id')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('featured')->default(false);
            $table->enum('visibility', ['public', 'authenticated', 'role_restricted'])->default('public');
            $table->jsonb('target_roles')->nullable();
            $table->uuid('author_id');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('helpdesk_categories')->onDelete('restrict');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('helpdesk_article_tag', function (Blueprint $table) {
            $table->uuid('article_id');
            $table->uuid('tag_id');

            $table->primary(['article_id', 'tag_id']);
            $table->foreign('article_id')->references('id')->on('helpdesk_articles')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('helpdesk_tags')->onDelete('cascade');
        });

        Schema::create('helpdesk_article_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('article_id');
            $table->string('title');
            $table->text('content_markdown');
            $table->text('excerpt')->nullable();
            $table->uuid('edited_by');
            $table->string('edit_notes')->nullable();
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('helpdesk_articles')->onDelete('cascade');
            $table->foreign('edited_by')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('helpdesk_article_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('article_id');
            $table->uuid('user_id')->nullable();
            $table->boolean('helpful');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('helpdesk_articles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_article_feedback');
        Schema::dropIfExists('helpdesk_article_revisions');
        Schema::dropIfExists('helpdesk_article_tag');
        Schema::dropIfExists('helpdesk_articles');
        Schema::dropIfExists('helpdesk_tags');
        Schema::dropIfExists('helpdesk_categories');
    }
};
