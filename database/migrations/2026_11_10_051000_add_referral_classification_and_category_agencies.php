<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Existing referrals stay unclassified until the intake cutover.
            $table->foreignUuid('category_id')->nullable()->constrained('case_categories')->restrictOnDelete();
            $table->text('concern')->nullable();
            $table->text('override_reason')->nullable();
        });

        Schema::create('case_category_agency', function (Blueprint $table) {
            $table->foreignUuid('case_category_id')->constrained('case_categories')->cascadeOnDelete();
            $table->foreignUuid('agcy_id')->constrained('agencies')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['case_category_id', 'agcy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_category_agency');

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['concern', 'override_reason']);
        });
    }
};
