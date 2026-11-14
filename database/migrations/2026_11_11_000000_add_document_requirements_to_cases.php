<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // slot => received | complied_onsite | for_compliance. Absent keys are not requirements.
            $table->jsonb('document_requirements')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('document_requirements');
        });
    }
};
