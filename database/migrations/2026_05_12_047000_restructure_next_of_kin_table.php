<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('next_of_kin', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
            $table->dropColumn('case_id');
        });

        Schema::table('next_of_kin', function (Blueprint $table) {
            $table->uuid('client_id');
            $table->string('middle_initial', 10)->nullable()->after('first_name');
            $table->boolean('is_primary')->default(false);
            $table->string('phone_number', 50)->nullable();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('next_of_kin', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn(['client_id', 'middle_initial', 'is_primary', 'phone_number', 'is_deleted', 'deleted_at', 'deleted_by']);
        });

        Schema::table('next_of_kin', function (Blueprint $table) {
            $table->uuid('case_id');
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });
    }
};
