<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen columns to accommodate encrypted values (ciphertext is ~2-4x plaintext)
        Schema::table('clients', function (Blueprint $table) {
            $table->text('date_of_birth')->nullable()->change();
        });

        Schema::table('client_addresses', function (Blueprint $table) {
            $table->text('street')->nullable()->change();
        });

        Schema::table('client_employments', function (Blueprint $table) {
            $table->text('employer_name')->nullable()->change();
            $table->text('position')->nullable()->change();
            $table->text('last_position')->nullable()->change();
            $table->text('country')->nullable()->change();
            $table->text('last_country')->nullable()->change();
        });

        Schema::table('next_of_kin', function (Blueprint $table) {
            $table->text('phone_number')->nullable()->change();
            $table->text('email')->nullable()->change();
            $table->text('full_address')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Note: reverting text back to string is safe because ciphertext won't fit
        // in VARCHAR(255). After decryption (removing the cast), run a data migration.
        // For now, down() intentionally omitted — revert by removing casts + re-encrypting.
    }
};
