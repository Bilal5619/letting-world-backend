<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Suspend / reactivate an app account. Suspending signs the person out of the app everywhere. */
trait SuspendsAccounts
{
    protected function suspendAccount(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $user->forceFill(['suspended_at' => now(), 'suspended_reason' => $data['reason']])->save();
        $user->tokens()->delete();
        $user->pushTokens()->delete();
        AdminAction::record('account_suspended', "Suspended {$user->fullName()}: {$data['reason']}", $user);

        return back()->with('status', "{$user->fullName()} has been suspended and signed out of the app.");
    }

    protected function reactivateAccount(User $user): RedirectResponse
    {
        $user->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
        AdminAction::record('account_reactivated', "Reactivated {$user->fullName()}", $user);

        return back()->with('status', "{$user->fullName()} can sign in again.");
    }

    /** Filters a people list by account state. */
    protected function filterByAccountState($query, ?string $state): void
    {
        match ($state) {
            'suspended' => $query->whereNotNull('suspended_at'),
            'active' => $query->whereNull('suspended_at'),
            'incomplete' => $query->whereNull('suspended_at')->whereNull('profile_completed_at'),
            'invited' => $query->whereNull('suspended_at')->where('must_change_password', true),
            default => null,
        };
    }
}
