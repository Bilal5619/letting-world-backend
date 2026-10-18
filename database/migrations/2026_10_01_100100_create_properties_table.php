<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('postcode', 10)->index();
            $table->enum('property_type', ['flat', 'house', 'hmo', 'bungalow', 'maisonette', 'studio', 'other']);
            $table->unsignedTinyInteger('bedrooms')->default(0);
            $table->unsignedTinyInteger('bathrooms')->default(0);
            $table->enum('furnishing', ['furnished', 'unfurnished', 'part_furnished']);
            // Decides whether a Gas Safety certificate is required.
            $table->boolean('has_gas')->default(false);
            $table->decimal('monthly_rent', 10, 2)->nullable();
            $table->enum('status', ['let', 'vacant', 'advertised', 'maintenance'])->default('vacant');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
