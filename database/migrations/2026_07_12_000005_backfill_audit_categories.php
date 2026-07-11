<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        // Category continuity must not depend on a manual post-deploy step:
        // legacy rows without a category would vanish from a Security-only
        // view. Volume is small enough to run inline.
        Artisan::call('audit:backfill-categories');
    }

    public function down(): void
    {
        // Intentionally left blank — categories are additive.
    }
};
