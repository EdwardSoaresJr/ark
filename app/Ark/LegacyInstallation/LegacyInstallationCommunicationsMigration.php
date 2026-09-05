<?php

namespace App\Ark\LegacyInstallation;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared helpers for upgrade migrations that preserve populated legacy authority.
 */
final class LegacyInstallationCommunicationsMigration
{
    /**
     * Keep populated integration columns intact for legacy installations.
     * Drop empty turnkey columns on fresh installs only.
     *
     * @param  list<string>  $columns
     */
    public static function preservePopulatedOrDropEmptyColumns(array $columns): void
    {
        if (! Schema::hasTable('shop_settings')) {
            return;
        }

        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn('shop_settings', $column),
        ));

        if ($existing === []) {
            return;
        }

        $hasData = false;

        foreach ($existing as $column) {
            if (DB::table('shop_settings')->whereNotNull($column)->where($column, '!=', '')->exists()) {
                $hasData = true;

                break;
            }
        }

        if ($hasData) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }
}
