<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Forward-only scrub migration retained for history. No longer drops Postmark authority.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Compatibility: legacy Postmark authority is preserved by later migrations.
    }

    public function down(): void
    {
        //
    }
};
