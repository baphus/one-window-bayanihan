<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_file_case_category', function (Blueprint $table) {
            $table->foreignUuid('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignUuid('case_category_id')->constrained('case_categories')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['case_id', 'case_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_file_case_category');
    }
};
