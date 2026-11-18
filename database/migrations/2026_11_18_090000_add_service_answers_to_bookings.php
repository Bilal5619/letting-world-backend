<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The landlord's answers to 11Done's booking questions (e.g. number of appliances, parking).
 * Sent to 11Done with the booking and offered again for the next renewal at that property.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('service_answers')->nullable()->after('provider_service_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('service_answers');
        });
    }
};
