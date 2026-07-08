<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('philippine_addresses');
    }

    public function down(): void
    {
        Schema::create('philippine_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('code', 20);
            $table->string('name', 255);
            $table->string('parent_code', 20)->nullable()->index();
            $table->timestamps();

            $table->index(['type', 'parent_code']);
            $table->unique(['type', 'code']);
        });
    }
};
