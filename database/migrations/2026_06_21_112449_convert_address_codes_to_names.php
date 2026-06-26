<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert numeric PSGC codes to human-readable names in address columns
     * for both client_addresses and next_of_kin tables.
     *
     * Columns mapped to reference types:
     *   region           → philippine_addresses.type = 'region'
     *   province         → philippine_addresses.type = 'province'
     *   city_municipality → philippine_addresses.type IN ('city', 'municipality')
     *   barangay         → philippine_addresses.type = 'barangay'
     *
     * Idempotent: rows that already have text names (not numeric codes) are
     * skipped because the WHERE IN (SELECT code ...) subquery won't match.
     */
    public function up(): void
    {
        DB::beginTransaction();

        try {
            $this->convertCodesToNames('client_addresses');
            $this->convertCodesToNames('next_of_kin');

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Reverse: convert text names back to numeric PSGC codes.
     * Only affects rows where the name exists in philippine_addresses.
     */
    public function down(): void
    {
        DB::beginTransaction();

        try {
            $this->convertNamesToCodes('client_addresses');
            $this->convertNamesToCodes('next_of_kin');

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Map address columns to their philippine_addresses reference types.
     *
     * @return array<string, list<string>>
     */
    private static function columnTypeMap(): array
    {
        return [
            'region' => ['region'],
            'province' => ['province'],
            'city_municipality' => ['city', 'municipality'],
            'barangay' => ['barangay'],
        ];
    }

    /**
     * Convert PSGC codes to human-readable names for the given table.
     */
    private function convertCodesToNames(string $table): void
    {
        foreach (self::columnTypeMap() as $column => $types) {
            $typeList = "'".implode("', '", $types)."'";

            DB::statement("
                UPDATE {$table}
                   SET {$column} = (
                       SELECT pa.name
                         FROM philippine_addresses pa
                        WHERE pa.code = {$table}.{$column}
                          AND pa.type IN ({$typeList})
                   )
                 WHERE {$column} IS NOT NULL
                   AND {$column} != ''
                   AND {$column} IN (
                       SELECT pa.code
                         FROM philippine_addresses pa
                        WHERE pa.type IN ({$typeList})
                   )
            ");
        }
    }

    /**
     * Convert human-readable names back to PSGC codes for the given table.
     */
    private function convertNamesToCodes(string $table): void
    {
        foreach (self::columnTypeMap() as $column => $types) {
            $typeList = "'".implode("', '", $types)."'";

            DB::statement("
                UPDATE {$table}
                   SET {$column} = (
                       SELECT pa.code
                         FROM philippine_addresses pa
                        WHERE pa.name = {$table}.{$column}
                          AND pa.type IN ({$typeList})
                   )
                 WHERE {$column} IS NOT NULL
                   AND {$column} != ''
                   AND {$column} IN (
                       SELECT pa.name
                         FROM philippine_addresses pa
                        WHERE pa.type IN ({$typeList})
                   )
            ");
        }
    }
};
