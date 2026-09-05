<?php

use App\Ark\LegacyInstallation\LegacyInstallationCommunicationsMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings')) {
            return;
        }

        LegacyInstallationCommunicationsMigration::preservePopulatedOrDropEmptyColumns([
            'partstech_base_url',
            'partstech_catalog_path',
            'partstech_username',
            'partstech_api_key',
            'partstech_password',
        ]);
    }

    public function down(): void
    {
        //
    }
};
