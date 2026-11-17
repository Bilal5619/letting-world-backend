<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\Certificate;
use App\Models\PushToken;
use App\Models\User;
use App\Support\ElevenDone;
use App\Support\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

/** Connections (11Done, Stripe, email), team-arranged prices, admin team, own password, audit log. */
class SettingsController extends Controller
{
    public function index(ElevenDone $elevenDone, Payments $payments): View
    {
        return view('admin.settings.index', [
            'elevenDone' => [
                'enabled' => $elevenDone->enabled(),
                'url' => config('services.elevendone.url'),
                'key' => config('services.elevendone.api_key'),
                'secret' => filled(config('services.elevendone.webhook_secret')),
                'webhook' => url('/api/integrations/11done/webhook'),
                'services' => collect(config('lettingworld.elevendone_services'))->filter()
                    ->map(fn ($id, $type) => ['type' => $type, 'label' => Certificate::typeLabel($type), 'id' => $id]),
                'sent' => Booking::where('sync_status', 'sent')->count(),
                'failed' => Booking::where('sync_status', 'failed')->whereIn('status', Booking::OPEN_STATUSES)->count(),
                'lastSync' => Booking::max('synced_at'),
            ],
            'test' => session('elevenDoneTest'),
            'stripeTest' => session('stripeTest'),
            'manualServices' => collect(config('lettingworld.service_prices'))
                ->reject(fn ($price, $type) => $elevenDone->enabled() && $elevenDone->serviceFor($type))
                ->map(fn ($price, $type) => ['label' => Certificate::typeLabel($type), 'price' => (float) $price]),
            'stripe' => $payments->configured(),
            'mail' => config('mail.default'),
            'pushDevices' => PushToken::count(),
            'admins' => User::where('role', User::ROLE_ADMIN)->orderBy('first_name')->get(),
            'log' => AdminAction::with('admin')->latest('created_at')->limit(25)->get(),
        ]);
    }

    public function testElevenDone(ElevenDone $elevenDone): RedirectResponse
    {
        if (! $elevenDone->enabled()) {
            return back()->with('elevenDoneTest', ['ok' => false, 'message' => '11Done isn’t configured. Set ELEVENDONE_URL and ELEVENDONE_API_KEY.']);
        }

        $started = microtime(true);
        try {
            $data = ElevenDone::attempt(fn () => $elevenDone->services());
        } catch (RuntimeException $e) {
            return back()->with('elevenDoneTest', ['ok' => false, 'message' => $e->getMessage()]);
        }

        return back()->with('elevenDoneTest', [
            'ok' => true,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'message' => 'Connected. 11Done offers '.count($data['services'] ?? []).' bookable services.',
            'names' => collect($data['services'] ?? [])->pluck('name', 'id')->all(),
        ]);
    }

    public function testStripe(Payments $payments): RedirectResponse
    {
        return back()->with('stripeTest', $payments->check());
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], ['email.unique' => 'An account with this email already exists.']);

        $admin = User::create($data + ['role' => User::ROLE_ADMIN, 'email_verified_at' => now()]);
        AdminAction::record('admin_created', "Added admin {$admin->fullName()} ({$admin->email})", $admin);

        return back()->with('status', "{$admin->fullName()} can now sign in to the admin panel.");
    }

    public function toggleAdmin(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->isAdmin(), 404);
        abort_if($admin->is($request->user()), 422, 'You can’t suspend your own account.');

        $suspend = ! $admin->isSuspended();
        $admin->forceFill(['suspended_at' => $suspend ? now() : null, 'suspended_reason' => $suspend ? 'Suspended by an admin' : null])->save();
        AdminAction::record($suspend ? 'admin_suspended' : 'admin_reactivated', ($suspend ? 'Suspended' : 'Reactivated')." admin {$admin->fullName()}", $admin);

        return back()->with('status', $suspend ? "{$admin->fullName()} can no longer sign in." : "{$admin->fullName()} can sign in again.");
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'confirmed', 'different:current_password', Password::min(10)->letters()->numbers()],
        ], [], ['new_password' => 'new password']);

        $request->user()->forceFill(['password' => $request->input('new_password')])->save();
        AdminAction::record('password_changed', 'Changed their password');

        return back()->with('status', 'Password updated.');
    }
}
