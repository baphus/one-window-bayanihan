<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->uuid('case_issue_id')->nullable()->after('category_id');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->foreign('case_issue_id')->references('id')->on('case_issues')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['case_issue_id']);
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('case_issue_id');
        });
    }
};
