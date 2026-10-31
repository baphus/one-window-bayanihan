<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Signed-in OFW owners reply without a capability link, so the message table
 * needs a sender kind that pairs a real user_id with an empty access_link_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement('ALTER TABLE referral_client_messages DROP CONSTRAINT IF EXISTS referral_client_messages_sender_check');
            DB::statement('ALTER TABLE referral_client_messages DROP CONSTRAINT IF EXISTS referral_client_messages_sender_kind_check');

            DB::statement(<<<'SQL'
                ALTER TABLE referral_client_messages
                ADD CONSTRAINT referral_client_messages_sender_check CHECK (
                    (sender_kind = 'AGENCY_USER' AND user_id IS NOT NULL AND access_link_id IS NULL)
                    OR (sender_kind = 'CLIENT_ACCESS' AND user_id IS NULL AND access_link_id IS NOT NULL)
                    OR (sender_kind = 'CLIENT_USER' AND user_id IS NOT NULL AND access_link_id IS NULL)
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE referral_client_messages
                ADD CONSTRAINT referral_client_messages_sender_kind_check CHECK (
                    sender_kind IN ('AGENCY_USER', 'CLIENT_ACCESS', 'CLIENT_USER')
                )
            SQL);
        });
    }

    /**
     * Refuse to narrow the constraint while CLIENT_USER rows exist: a failing
     * ALTER is preferable to deleting owner-authored replies.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            DB::statement('ALTER TABLE referral_client_messages DROP CONSTRAINT IF EXISTS referral_client_messages_sender_check');
            DB::statement('ALTER TABLE referral_client_messages DROP CONSTRAINT IF EXISTS referral_client_messages_sender_kind_check');

            DB::statement(<<<'SQL'
                ALTER TABLE referral_client_messages
                ADD CONSTRAINT referral_client_messages_sender_check CHECK (
                    (sender_kind = 'AGENCY_USER' AND user_id IS NOT NULL AND access_link_id IS NULL)
                    OR (sender_kind = 'CLIENT_ACCESS' AND user_id IS NULL AND access_link_id IS NOT NULL)
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE referral_client_messages
                ADD CONSTRAINT referral_client_messages_sender_kind_check CHECK (
                    sender_kind IN ('AGENCY_USER', 'CLIENT_ACCESS')
                )
            SQL);
        });
    }
};
