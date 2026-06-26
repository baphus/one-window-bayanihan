<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. agencies
        Schema::create('agencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('short')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('contact_info', 255)->nullable();
            $table->text('map_link')->nullable();
            $table->text('logo_url')->nullable();
            $table->text('location_query')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 2. services
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('agcy_id')->nullable();
            $table->integer('processing_days')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('agcy_id')->references('id')->on('agencies')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_processing_days_check CHECK (processing_days IS NULL OR (processing_days >= 0 AND processing_days <= 365))');
        }

        // 3. service_requirements
        Schema::create('service_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required');
            $table->uuid('service_id');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 4. case_statuses
        Schema::create('case_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        DB::table('case_statuses')->insert([
            ['id' => Str::uuid()->toString(), 'name' => 'Open',           'slug' => 'OPEN',           'type' => 'case',     'color' => '#22c55e', 'sort_order' => 1, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Closed',         'slug' => 'CLOSED',         'type' => 'case',     'color' => '#64748b', 'sort_order' => 2, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Pending',        'slug' => 'PENDING',        'type' => 'referral', 'color' => '#f59e0b', 'sort_order' => 1, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Processing',     'slug' => 'PROCESSING',     'type' => 'referral', 'color' => '#3b82f6', 'sort_order' => 2, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'For Compliance', 'slug' => 'FOR_COMPLIANCE', 'type' => 'referral', 'color' => '#f97316', 'sort_order' => 3, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Completed',      'slug' => 'COMPLETED',      'type' => 'referral', 'color' => '#22c55e', 'sort_order' => 4, 'is_system' => true],
            ['id' => Str::uuid()->toString(), 'name' => 'Rejected',       'slug' => 'REJECTED',       'type' => 'referral', 'color' => '#ef4444', 'sort_order' => 5, 'is_system' => true],
        ]);

        // 5. case_categories
        Schema::create('case_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 6. case_issues
        Schema::create('case_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 7. system_settings
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('category')->nullable();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        DB::table('system_settings')->insert([
            'key' => 'debug_otp_enabled',
            'value' => 'false',
        ]);

        // 8. philippine_addresses
        Schema::create('philippine_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('code', 20);
            $table->string('name', 255);
            $table->string('parent_code', 20)->nullable()->index();
            $table->timestamps();

            $table->index(['type', 'parent_code']);
            $table->unique(['type', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('philippine_addresses');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('case_issues');
        Schema::dropIfExists('case_categories');
        Schema::dropIfExists('case_statuses');
        Schema::dropIfExists('service_requirements');
        Schema::dropIfExists('services');
        Schema::dropIfExists('agencies');
    }
};
