<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('short')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->text('logo_url')->nullable();
            $table->text('location_query')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['short', 'slug', 'logo_url', 'location_query', 'is_active']);
        });
    }
};
