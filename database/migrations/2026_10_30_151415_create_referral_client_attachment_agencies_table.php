<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_client_attachment_agencies', function (Blueprint $table): void {
            $table->uuid('attachment_id');
            $table->uuid('agency_id');
            $table->timestamps();
            $table->primary(['attachment_id', 'agency_id']);
            $table->foreign('attachment_id')->references('id')->on('referral_client_message_attachments')->cascadeOnDelete();
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_client_attachment_agencies');
    }
};
