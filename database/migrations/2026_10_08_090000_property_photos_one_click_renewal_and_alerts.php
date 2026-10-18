<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('reference', 100)->nullable()->after('landlord_id'); // property name / reference
            $table->text('notes')->nullable()->after('monthly_rent');
            $table->string('photo_path')->nullable()->after('notes');
            $table->string('photo_mime', 100)->nullable()->after('photo_path');
        });

        // Tenants can now be assigned first and the tenancy details filled in later.
        Schema::table('tenancies', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->decimal('monthly_rent', 10, 2)->nullable()->change();
            $table->unsignedTinyInteger('rent_due_day')->nullable()->change();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('access', ['tenant', 'landlord', 'other'])->default('landlord')->after('time_slot');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded', 'not_charged'])
                ->default('pending')->after('price');
            $table->string('payment_intent_id')->nullable()->after('payment_status');
            $table->string('card_brand', 30)->nullable()->after('payment_intent_id');
            $table->string('card_last4', 4)->nullable()->after('card_brand');
        });

        // Remembers which automatic alerts were already sent, so each one goes out once.
        Schema::create('sent_alerts', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject'); // certificate or tenancy
            $table->string('alert_key', 40); // e.g. "60", "30", "expired", "expired:3"
            $table->timestamp('sent_at');
            $table->unique(['subject_type', 'subject_id', 'alert_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_alerts');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['access', 'payment_status', 'payment_intent_id', 'card_brand', 'card_last4']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['reference', 'notes', 'photo_path', 'photo_mime']);
        });
    }
};
