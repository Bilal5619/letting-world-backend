<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bookings are fulfilled by 11Done: track the link, its sync state and what comes back. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // 11Done's 2-hour slots ("08:00 AM - 10:00 AM") replace morning/afternoon/any.
            $table->string('time_slot', 40)->nullable()->default(null)->change();
            // Null when the tenant picks the time through 11Done.
            $table->date('preferred_date')->nullable()->change();
            $table->enum('scheduling', ['landlord', 'tenant'])->default('landlord')->after('preferred_date');

            $table->unsignedBigInteger('provider_service_id')->nullable()->after('provider');
            $table->string('provider_status', 30)->nullable()->after('provider_reference');
            // not_required (demo / no 11Done), pending, sent, failed
            $table->string('sync_status', 20)->default('not_required')->index()->after('provider_status');
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->unsignedSmallInteger('sync_attempts')->default(0)->after('sync_error');
            $table->timestamp('synced_at')->nullable()->after('sync_attempts');

            $table->string('engineer_name')->nullable()->after('synced_at');
            $table->date('scheduled_date')->nullable()->after('engineer_name');
            $table->string('scheduled_slot', 40)->nullable()->after('scheduled_date');
            $table->timestamp('completed_at')->nullable()->after('scheduled_slot');
            $table->foreignId('result_certificate_id')->nullable()->after('certificate_id')->constrained('certificates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('result_certificate_id');
            $table->dropColumn([
                'scheduling', 'provider_service_id', 'provider_status', 'sync_status', 'sync_error', 'sync_attempts',
                'synced_at', 'engineer_name', 'scheduled_date', 'scheduled_slot', 'completed_at',
            ]);
        });
    }
};
