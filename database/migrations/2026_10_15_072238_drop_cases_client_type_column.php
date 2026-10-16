<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('cases') && Schema::hasColumn('cases', 'client_type')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropColumn('client_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cases') && ! Schema::hasColumn('cases', 'client_type')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->string('client_type', 20)->nullable();
            });
        }
    }
};
