<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * role:landlord / role:tenant — restricts a route to one account type.
 * Landlords must also have completed their mandatory profile, and tenants must
 * have replaced their temporary password, before they can use these routes.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== $role) {
            abort(403, 'You do not have access to this.');
        }
        if ($user->isSuspended()) {
            abort(403, 'This account has been suspended. Please contact Letting World.');
        }
        if ($user->isLandlord() && ! $user->profile_completed_at) {
            abort(403, 'Please complete your profile first.');
        }
        if ($user->must_change_password) {
            abort(403, 'Please set a new password first.');
        }

        return $next($request);
    }
}
