<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FileController;
use App\Http\Controllers\Admin\LandlordController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

/*
 * Admin panel — /admin. Loaded from bootstrap/app.php with the "web" middleware,
 * the /admin prefix and the "admin." route-name prefix.
 */

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');
    Route::get('files/{kind}/{id}', FileController::class)->name('files')
        ->whereIn('kind', ['certificate', 'document', 'property', 'maintenance-photo']);

    Route::controller(BookingController::class)->prefix('bookings')->name('bookings.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{booking}', 'show')->name('show');
        Route::put('{booking}/notes', 'notes')->name('notes');
        Route::post('{booking}/schedule', 'schedule')->name('schedule');
        Route::post('{booking}/complete', 'complete')->name('complete');
        Route::post('{booking}/certificate', 'attachCertificate')->name('certificate');
        Route::post('{booking}/cancel', 'cancel')->name('cancel');
        Route::post('{booking}/retry', 'retry')->name('retry');
        Route::post('{booking}/refresh', 'refresh')->name('refresh');
    });

    Route::controller(LandlordController::class)->prefix('landlords')->name('landlords.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{landlord}', 'show')->name('show');
        Route::post('{landlord}/suspend', 'suspend')->name('suspend');
        Route::post('{landlord}/reactivate', 'reactivate')->name('reactivate');
    });

    Route::controller(TenantController::class)->prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{tenant}', 'show')->name('show');
        Route::post('{tenant}/suspend', 'suspend')->name('suspend');
        Route::post('{tenant}/reactivate', 'reactivate')->name('reactivate');
    });

    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');

    Route::get('certificates', CertificateController::class)->name('certificates.index');

    Route::get('maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('maintenance/{maintenance}', [MaintenanceController::class, 'show'])->name('maintenance.show');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/export', [PaymentController::class, 'export'])->name('payments.export');

    Route::controller(SettingsController::class)->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('elevendone/test', 'testElevenDone')->name('elevendone.test');
        Route::post('stripe/test', 'testStripe')->name('stripe.test');
        Route::post('admins', 'storeAdmin')->name('admins.store');
        Route::post('admins/{admin}/toggle', 'toggleAdmin')->name('admins.toggle');
        Route::put('password', 'updatePassword')->name('password');
    });
});
