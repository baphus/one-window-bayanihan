<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retired Gen 1 SERVQUAL feedback stack (see
        // docs/FEEDBACK_FEATURE_RESEARCH_2026-09-16.md). The live feedback
        // feature runs on the survey_* tables; these four tables have no
        // models, routes, controllers, or write paths — only export reads.
        // Child tables first: feedback_servqual_responses and
        // feedback_invitations reference feedback.
        Schema::dropIfExists('feedback_servqual_responses');
        Schema::dropIfExists('feedback_invitations');
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('servqual_configs');
    }

    public function down(): void
    {
        // Do not recreate: the Gen 1 stack is retired. Historical export data
        // should be restored from a backup, not from a schema stub.
    }
};
