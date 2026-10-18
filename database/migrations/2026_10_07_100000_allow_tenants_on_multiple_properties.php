<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A tenant can now be assigned to several properties (one tenancy per property). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancies', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id']);
            $table->unique(['property_id', 'tenant_id']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('postcode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->timestamp('archived_at')->nullable()->index()->after('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->unique()->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'archived_at']);
        });

        Schema::table('tenancies', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['property_id', 'tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
