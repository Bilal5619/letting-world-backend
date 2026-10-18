<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin panel: an admin role, account suspension, internal booking notes (manual jobs)
 * and an audit log of what admins did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['landlord', 'tenant', 'admin'])->change();
            // Suspended accounts can't sign in to the app; their sessions are revoked.
            $table->timestamp('suspended_at')->nullable()->after('last_login_at');
            $table->string('suspended_reason')->nullable()->after('suspended_at');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Internal notes for the Letting World team (never shown to landlords).
            $table->text('admin_notes')->nullable()->after('access_notes');
        });

        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50)->index();
            $table->string('subject_type', 30)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('admin_notes'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspended_reason']);
            $table->enum('role', ['landlord', 'tenant'])->change();
        });
    }
};
