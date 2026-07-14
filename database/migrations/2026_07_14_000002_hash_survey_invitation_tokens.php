<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('survey_responses')
            ->select('survey_invitation_id', 'survey_question_id')
            ->whereNotNull('survey_question_id')
            ->groupBy('survey_invitation_id', 'survey_question_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot add survey response uniqueness constraint: duplicate invitation/question %s/%s exists.',
                $duplicates[0]->survey_invitation_id,
                $duplicates[0]->survey_question_id,
            ));
        }

        Schema::table('survey_invitations', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('token', 64)->nullable()->change();
        });

        Schema::table('survey_responses', function (Blueprint $table) {
            $table->unique(['survey_invitation_id', 'survey_question_id'], 'survey_responses_invitation_question_unique');
        });

        DB::table('survey_invitations')->whereNotNull('token')->orderBy('id')->chunkById(500, function ($invitations): void {
            foreach ($invitations as $invitation) {
                DB::table('survey_invitations')->where('id', $invitation->id)->update([
                    'token_hash' => hash('sha256', $invitation->token),
                ]);
            }
        }, 'id');
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropUnique('survey_responses_invitation_question_unique');
        });
        Schema::table('survey_invitations', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn('token_hash');
            // Hash-only inserts intentionally leave the legacy column nullable.
        });
    }
};
