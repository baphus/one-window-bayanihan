<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            // Resend's message id, taken from the X-Resend-Email-ID header the
            // transport stamps on the message. Nullable because the log, smtp
            // and array transports never set it.
            $table->string('provider_message_id')->nullable()->after('job_uuid');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');

            $table->index('provider_message_id');
        });

        // Append-only record of provider delivery events. Kept separate from
        // email_logs so that concurrent webhooks (Resend emits sent/delivered/
        // opened in close succession) cannot lose each other's writes the way
        // read-modify-write on a shared JSON column would.
        Schema::create('email_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('email_log_id')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('event_type');
            $table->timestamp('occurred_at')->nullable();

            // Svix reuses the same id across retries, so a unique constraint is
            // all the idempotency bookkeeping this needs.
            $table->string('svix_id')->unique();
            $table->jsonb('payload');
            $table->timestamps();

            // nullOnDelete, not cascade: events outlive a pruned email_logs row
            // on purpose, so the audit trail does not vanish with its index row.
            $table->foreign('email_log_id')
                ->references('id')
                ->on('email_logs')
                ->nullOnDelete();

            $table->index('email_log_id');
            $table->index('provider_message_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_events');

        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['provider_message_id']);
            $table->dropColumn(['provider_message_id', 'delivered_at']);
        });
    }
};
