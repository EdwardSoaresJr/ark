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
            'openai_api_key',
            'openai_transcription_model',
            'openai_analysis_model',
        ]);
    }

    public function down(): void
    {
        //
    }
};
