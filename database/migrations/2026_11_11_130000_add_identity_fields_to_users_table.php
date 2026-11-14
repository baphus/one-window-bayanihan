<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured identity for every account, not only case filers. `name`
     * stays as the composed display string so existing readers (mail, audit
     * labels, search) keep working; User recomposes it from these parts.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 255)->nullable()->after('name');
            $table->string('middle_name', 255)->nullable()->after('first_name');
            $table->string('last_name', 255)->nullable()->after('middle_name');
            $table->string('suffix', 50)->nullable()->after('last_name');
            // Encrypted by the model cast, so plaintext length is not the column limit.
            $table->text('date_of_birth')->nullable()->after('suffix');
            $table->string('sex', 10)->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex']);
        });
    }
};
