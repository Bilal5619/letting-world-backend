<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Google sign-in for the mobile app (landlords only).
 *
 * 1. App opens  GET /api/auth/google/redirect?redirect_uri=lettingworld://auth/callback
 * 2. Google redirects to GET /api/auth/google/callback
 * 3. We redirect back to the app: lettingworld://auth/callback?token=...  (or ?error=...)
 */
class GoogleAuthController extends Controller
{
    /** Lets the app hide "Continue with Google" until GOOGLE_CLIENT_ID / SECRET are set. */
    public function status(): JsonResponse
    {
        return response()->json(['enabled' => $this->configured()]);
    }

    public function redirect(Request $request): RedirectResponse
    {
        $appRedirect = (string) $request->query('redirect_uri');
        abort_unless($this->isAllowedAppRedirect($appRedirect), 422, 'Invalid redirect_uri.');

        if (! $this->configured()) {
            return $this->backToApp($appRedirect, ['error' => 'Google sign-in is not available yet. Please log in with your email and password.']);
        }

        // The app's return URL travels through Google inside an encrypted state value.
        return Socialite::driver('google')
            ->stateless()
            ->with(['state' => encrypt($appRedirect), 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $appRedirect = decrypt((string) $request->query('state'));
        } catch (DecryptException) {
            abort(400, 'Invalid state.');
        }
        abort_unless($this->isAllowedAppRedirect($appRedirect), 400, 'Invalid state.');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $e) {
            Log::warning('Google sign-in failed', ['error' => $e->getMessage()]);

            return $this->backToApp($appRedirect, ['error' => 'Google sign-in was cancelled or failed.']);
        }

        $email = strtolower((string) $googleUser->getEmail());
        if ($email === '') {
            return $this->backToApp($appRedirect, ['error' => 'Your Google account has no email address.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user?->isTenant()) {
            return $this->backToApp($appRedirect, [
                'error' => 'Tenant accounts sign in with the email and password from your landlord.',
            ]);
        }
        if ($user?->isAdmin()) {
            return $this->backToApp($appRedirect, ['error' => 'Admin accounts sign in at the admin panel.']);
        }
        if ($user?->isSuspended()) {
            return $this->backToApp($appRedirect, ['error' => 'This account has been suspended. Please contact Letting World.']);
        }

        if (! $user) {
            [$first, $last] = $this->splitName($googleUser);
            $user = User::create([
                'role' => User::ROLE_LANDLORD,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'auth_provider' => 'google',
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
            ]);
        } elseif (! $user->google_id) {
            // Existing email/password landlord — link Google (Google has verified the email).
            $user->forceFill(['google_id' => $googleUser->getId(), 'email_verified_at' => $user->email_verified_at ?? now()]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->backToApp($appRedirect, ['token' => $user->createToken('mobile')->plainTextToken]);
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    private function isAllowedAppRedirect(string $url): bool
    {
        foreach (config('services.google.app_redirect_prefixes') as $prefix) {
            if ($prefix !== '' && Str::startsWith($url, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function backToApp(string $appRedirect, array $params): RedirectResponse
    {
        $separator = str_contains($appRedirect, '?') ? '&' : '?';

        return redirect()->away($appRedirect.$separator.http_build_query($params));
    }

    /** @return array{0: string, 1: string} */
    private function splitName($googleUser): array
    {
        $raw = $googleUser->user ?? [];
        $first = $raw['given_name'] ?? null;
        $last = $raw['family_name'] ?? null;
        if (! $first) {
            $parts = explode(' ', trim((string) $googleUser->getName()), 2);
            $first = $parts[0] ?: 'Landlord';
            $last = $last ?? ($parts[1] ?? '');
        }

        return [$first, $last ?? ''];
    }
}
