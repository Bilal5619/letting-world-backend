<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40); // key from config('lettingworld.certificate_types')
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('provider_name')->nullable();
            $table->string('provider_reg')->nullable(); // e.g. Gas Safe / NICEIC number
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['property_id', 'type']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // Set for tenancy documents (e.g. the tenancy agreement); null for property-wide documents.
            $table->foreignId('tenancy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40); // key from config('lettingworld.document_categories')
            $table->string('title');
            $table->boolean('shared_with_tenant')->default(true);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 100);
            $table->unsignedInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_type', 40); // certificate type being renewed
            $table->date('preferred_date');
            $table->enum('time_slot', ['morning', 'afternoon', 'any'])->default('any');
            $table->text('access_notes')->nullable();
            $table->boolean('notify_tenant')->default(true);
            $table->enum('status', ['requested', 'confirmed', 'completed', 'cancelled'])->default('requested')->index();
            $table->decimal('price', 10, 2)->nullable(); // set once the provider confirms
            $table->string('provider', 30)->default('11done');
            $table->string('provider_reference')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('certificates');
    }
};
