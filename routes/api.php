<?php

use App\Http\Controllers\Api\AgreementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ElevenDoneWebhookController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\TenancyController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TenantMaintenanceController;
use App\Http\Controllers\Api\TenantPropertyController;
use Illuminate\Support\Facades\Route;

// ---------- Public ----------
Route::middleware('throttle:10,1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
});
Route::get('auth/google/status', [GoogleAuthController::class, 'status']);
Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);

// 11Done → Letting World booking updates (HMAC-signed).
Route::post('integrations/11done/webhook', ElevenDoneWebhookController::class)->middleware('throttle:300,1');

// Private files — only via signed, expiring links handed out to authorised users.
Route::get('files/{kind}/{id}', FileController::class)->middleware('signed')->name('files.show');

// ---------- Any signed-in user ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:10,1');
    Route::put('profile', [ProfileController::class, 'update']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::post('devices', [DeviceController::class, 'store']);
    Route::delete('devices', [DeviceController::class, 'destroy']);

    // ---------- Landlords (with a completed profile) ----------
    Route::middleware('role:landlord')->group(function () {
        Route::get('dashboard', DashboardController::class);

        Route::apiResource('properties', PropertyController::class);
        Route::post('properties/{property}/archive', [PropertyController::class, 'archive']);
        Route::post('properties/{property}/restore', [PropertyController::class, 'restore']);
        Route::post('properties/{property}/photo', [PropertyController::class, 'uploadPhoto']);
        Route::delete('properties/{property}/photo', [PropertyController::class, 'deletePhoto']);
        Route::get('properties/{property}/history', [PropertyController::class, 'history']);

        Route::apiResource('tenants', TenantController::class);
        Route::get('properties/{property}/tenancies', [TenancyController::class, 'index']);
        Route::post('properties/{property}/tenants', [TenancyController::class, 'assignMany']);
        Route::post('tenants/{tenant}/tenancies', [TenancyController::class, 'store']);
        Route::put('tenancies/{tenancy}', [TenancyController::class, 'update']);
        Route::delete('tenancies/{tenancy}', [TenancyController::class, 'destroy']);

        Route::get('certificates', [CertificateController::class, 'index']);
        Route::post('properties/{property}/certificates', [CertificateController::class, 'store']);
        Route::delete('certificates/{certificate}', [CertificateController::class, 'destroy']);

        Route::get('properties/{property}/documents', [DocumentController::class, 'index']);
        Route::post('properties/{property}/documents', [DocumentController::class, 'store']);
        Route::post('documents/{document}/replace', [DocumentController::class, 'replace']);
        Route::post('documents/{document}/archive', [DocumentController::class, 'archive']);
        Route::post('documents/{document}/restore', [DocumentController::class, 'restore']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

        Route::get('properties/{property}/agreements', [AgreementController::class, 'index']);
        Route::post('properties/{property}/agreements', [AgreementController::class, 'store']);
        Route::put('agreements/{agreement}', [AgreementController::class, 'update']);
        Route::post('agreements/{agreement}/contract', [AgreementController::class, 'uploadContract']);
        Route::delete('agreements/{agreement}', [AgreementController::class, 'destroy']);

        Route::get('maintenance', [MaintenanceController::class, 'index']);
        Route::post('properties/{property}/maintenance', [MaintenanceController::class, 'store']);
        Route::get('maintenance/{maintenance}', [MaintenanceController::class, 'show']);
        Route::post('maintenance/{maintenance}/updates', [MaintenanceController::class, 'addUpdate']);
        Route::delete('maintenance/{maintenance}', [MaintenanceController::class, 'destroy']);

        Route::get('booking-services', [BookingController::class, 'services']);
        Route::get('bookings', [BookingController::class, 'index']);
        // POST carries the landlord's answers to 11Done's questions; GET kept for older app builds.
        Route::match(['get', 'post'], 'bookings/quote', [BookingController::class, 'quote']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);

        Route::get('payment-methods', [PaymentMethodController::class, 'index']);
        Route::post('payment-methods/setup', [PaymentMethodController::class, 'setup']);
        Route::post('payment-methods/{paymentMethod}/default', [PaymentMethodController::class, 'makeDefault']);
        Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);
    });

    // ---------- Tenants (after replacing their temporary password) ----------
    Route::middleware('role:tenant')->prefix('tenant')->group(function () {
        Route::get('properties', [TenantPropertyController::class, 'index']);
        Route::get('properties/{property}', [TenantPropertyController::class, 'show']);
        Route::get('properties/{property}/maintenance', [TenantMaintenanceController::class, 'index']);
        Route::post('properties/{property}/maintenance', [TenantMaintenanceController::class, 'store']);
        Route::get('maintenance/{maintenance}', [TenantMaintenanceController::class, 'show']);
        Route::post('maintenance/{maintenance}/updates', [TenantMaintenanceController::class, 'addUpdate']);
    });
});
