<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
        });

        $statuses = [
            ['id' => Str::uuid()->toString(), 'name' => 'Open',           'slug' => 'OPEN',           'type' => 'case',     'color' => '#22c55e', 'sort_order' => 1, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Closed',         'slug' => 'CLOSED',         'type' => 'case',     'color' => '#64748b', 'sort_order' => 2, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Pending',        'slug' => 'PENDING',        'type' => 'referral', 'color' => '#f59e0b', 'sort_order' => 1, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Processing',     'slug' => 'PROCESSING',     'type' => 'referral', 'color' => '#3b82f6', 'sort_order' => 2, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'For Compliance', 'slug' => 'FOR_COMPLIANCE', 'type' => 'referral', 'color' => '#f97316', 'sort_order' => 3, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Completed',      'slug' => 'COMPLETED',      'type' => 'referral', 'color' => '#22c55e', 'sort_order' => 4, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Rejected',       'slug' => 'REJECTED',       'type' => 'referral', 'color' => '#ef4444', 'sort_order' => 5, 'is_system' => true],
        ];

        DB::table('case_statuses')->insert($statuses);
    }

    public function down(): void
    {
        Schema::dropIfExists('case_statuses');
    }
};
