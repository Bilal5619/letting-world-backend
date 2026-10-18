<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('whatsapp', 32)->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('postcode', 10)->index();
            $table->enum('landlord_type', ['individual', 'joint', 'company']);
            $table->string('company_name')->nullable();
            $table->string('company_number', 20)->nullable();
            $table->string('registration_number')->nullable();
            $table->unsignedInteger('properties_owned');
            $table->enum('experience', ['first_time', 'experienced', 'portfolio']);
            $table->enum('management', ['self', 'agent']);
            $table->enum('preferred_contact', ['email', 'phone', 'whatsapp', 'sms']);
            $table->string('hear_about_us')->nullable();
            $table->timestamp('terms_accepted_at');
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('marketing_consent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_profiles');
    }
};
