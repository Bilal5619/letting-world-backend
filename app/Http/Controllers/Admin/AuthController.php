<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Admin sign-in (session based, separate from the app's API tokens). */
class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $email = strtolower(trim($data['email']));
        $key = 'admin-login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $admin = User::where('email', $email)->where('role', User::ROLE_ADMIN)->first();

        if (! $admin || ! $admin->password || ! Hash::check($data['password'], $admin->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These details don’t match an admin account.']);
        }
        if ($admin->isSuspended()) {
            throw ValidationException::withMessages(['email' => 'This admin account has been suspended.']);
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();
        $admin->forceFill(['last_login_at' => now()])->save();
        AdminAction::record('signed_in', 'Signed in to the admin panel');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'You’ve been signed out.');
    }
}
