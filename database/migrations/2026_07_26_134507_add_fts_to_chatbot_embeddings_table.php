<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Retired chatbot vector infrastructure. Keep the migration identity for
    // deployed databases; fresh installs need no extension or embedding table.
    // Existing data is intentionally retained for application rollback.
    public function up(): void {}

    public function down(): void {}
};
