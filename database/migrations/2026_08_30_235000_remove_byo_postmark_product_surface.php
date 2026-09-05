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
            'postmark_token',
            'postmark_message_stream_id',
            'email_provider',
        ]);
    }

    public function down(): void
    {
        //
    }
};
