<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- Maintenance ----------
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // The tenant who reported it (null when the landlord logged it).
            $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 30); // config('lettingworld.maintenance_categories')
            $table->enum('priority', ['low', 'normal', 'urgent', 'emergency'])->default('normal');
            $table->enum('status', ['reported', 'in_progress', 'scheduled', 'resolved', 'closed'])->default('reported')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_mime', 100);
            $table->unsignedInteger('file_size');
            $table->timestamps();
        });

        // Status changes and notes — the conversation on a repair.
        Schema::create('maintenance_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->nullable(); // set when this update changed the status
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // ---------- Tenancy agreements (one contract, several tenants) ----------
        Schema::create('tenancy_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_rent', 10, 2)->nullable();
            $table->unsignedTinyInteger('rent_due_day')->nullable();
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->string('deposit_scheme', 100)->nullable();
            $table->string('deposit_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tenancy_agreement_tenant', function (Blueprint $table) {
            $table->foreignId('tenancy_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['tenancy_agreement_id', 'tenant_id']);
        });

        // ---------- Documents: archive + link to an agreement (the signed contract) ----------
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('tenancy_agreement_id')->nullable()->after('tenancy_id')->constrained()->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('shared_with_tenant')->index();
        });

        // ---------- Property history (written automatically by model events) ----------
        Schema::create('property_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('title');
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // ---------- Push notifications ----------
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique(); // ExponentPushToken[...]
            $table->string('platform', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('property_events');
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenancy_agreement_id');
            $table->dropColumn('archived_at');
        });
        Schema::dropIfExists('tenancy_agreement_tenant');
        Schema::dropIfExists('tenancy_agreements');
        Schema::dropIfExists('maintenance_updates');
        Schema::dropIfExists('maintenance_photos');
        Schema::dropIfExists('maintenance_requests');
    }
};
