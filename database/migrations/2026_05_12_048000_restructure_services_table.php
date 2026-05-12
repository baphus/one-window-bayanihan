<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add columns first
        Schema::table('services', function (Blueprint $table) {
            $table->uuid('agcy_id')->nullable();
            $table->integer('processing_days')->nullable();
        });

        // 2. Migrate data from agency_service pivot table
        if (Schema::hasTable('agency_service')) {
            $pivotRows = DB::table('agency_service')->get();

            $processed = [];
            foreach ($pivotRows as $row) {
                if (!isset($processed[$row->service_id])) {
                    // First occurrence — update the existing service record
                    DB::table('services')
                        ->where('id', $row->service_id)
                        ->update([
                            'agcy_id' => $row->agency_id,
                            'processing_days' => $row->processing_days,
                        ]);
                    $processed[$row->service_id] = true;
                } else {
                    // Duplicate the service record for additional agency
                    $original = DB::table('services')->where('id', $row->service_id)->first();
                    if ($original) {
                        $newId = Illuminate\Support\Str::uuid();
                        DB::table('services')->insert([
                            'id' => (string) $newId,
                            'name' => $original->name,
                            'description' => $original->description,
                            'agcy_id' => $row->agency_id,
                            'processing_days' => $row->processing_days,
                            'is_deleted' => $original->is_deleted,
                            'deleted_at' => $original->deleted_at,
                            'deleted_by' => $original->deleted_by,
                            'created_at' => $original->created_at,
                            'updated_at' => $original->updated_at,
                        ]);
                    }
                }
            }

            Schema::dropIfExists('agency_service');
        }

        // 3. Add FK constraint (nullable for existing rows without pivot entry)
        Schema::table('services', function (Blueprint $table) {
            $table->foreign('agcy_id')->references('id')->on('agencies')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        // Restore pivot table
        Schema::create('agency_service', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id');
            $table->uuid('service_id');
            $table->text('required_documents')->nullable();
            $table->integer('processing_days')->nullable();
            $table->timestamps();

            $table->foreign('agency_id')->references('id')->on('agencies')->onDelete('restrict');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');
            $table->unique(['agency_id', 'service_id']);
        });

        // Re-populate pivot from services table
        $services = DB::table('services')->whereNotNull('agcy_id')->get();
        foreach ($services as $svc) {
            DB::table('agency_service')->insert([
                'id' => (string) Illuminate\Support\Str::uuid(),
                'agency_id' => $svc->agcy_id,
                'service_id' => $svc->id,
                'processing_days' => $svc->processing_days,
                'created_at' => $svc->created_at,
                'updated_at' => $svc->updated_at,
            ]);
        }

        // Drop columns
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['agcy_id']);
            $table->dropColumn(['agcy_id', 'processing_days']);
        });
    }
};
