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
            'twilio_account_sid',
            'twilio_auth_token',
            'twilio_api_key_sid',
            'twilio_api_key_secret',
            'twilio_voice_twiml_app_sid',
            'twilio_fcm_credential_sid',
            'twilio_apns_voip_credential_sid',
        ]);
    }

    public function down(): void
    {
        //
    }
};
