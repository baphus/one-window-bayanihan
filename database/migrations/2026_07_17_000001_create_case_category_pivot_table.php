<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_category', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('case_id');
            $table->uuid('case_category_id');
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
            $table->foreign('case_category_id')->references('id')->on('case_categories')->onDelete('restrict');
            $table->index('case_id');
            $table->index('case_category_id');
            $table->unique(['case_id', 'case_category_id']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO case_category (case_id, case_category_id, created_at, updated_at)
            SELECT id, category_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM cases
            WHERE category_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('case_category');
    }
};
