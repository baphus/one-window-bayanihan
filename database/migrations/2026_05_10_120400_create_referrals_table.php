<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('required_services');
            $table->text('notes')->nullable();
            $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'REJECTED', 'FOR COMPLIANCE'])->default('PENDING');
            $table->text('decision')->nullable();
            $table->uuid('case_id');
            $table->uuid('agcy_id');
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('restrict');
            $table->foreign('agcy_id')->references('id')->on('agencies')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
