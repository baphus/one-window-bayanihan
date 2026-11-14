<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_case_category', function (Blueprint $table) {
            $table->foreignUuid('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignUuid('case_category_id')->constrained('case_categories')->restrictOnDelete();
            $table->primary(['referral_id', 'case_category_id']);
        });
        DB::statement('INSERT INTO referral_case_category (referral_id, case_category_id) SELECT id, category_id FROM referrals WHERE category_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_case_category');
    }
};
