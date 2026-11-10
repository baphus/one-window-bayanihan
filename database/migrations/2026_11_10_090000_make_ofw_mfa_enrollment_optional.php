<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemSetting::setValue('mfa_role_ofw_required', false);
    }

    public function down(): void
    {
        // Preserve the new policy and any administrator changes.
    }
};
