{{-- Suspend / reactivate an app account. Expects $user, $suspendRoute, $reactivateRoute. --}}
@if ($user->isSuspended())
    <form method="POST" action="{{ $reactivateRoute }}">@csrf
        <x-admin.button type="submit" icon="rotate-ccw">Reactivate account</x-admin.button>
    </form>
@else
    <x-admin.modal name="suspend" :action="$suspendRoute" icon="ban" trigger="Suspend" trigger-variant="ghost" variant="danger" confirm="Suspend account"
        title="Suspend {{ $user->fullName() }}?" message="They’ll be signed out of the app on every device and can’t sign in again until you reactivate them. Their data is kept.">
        <x-admin.field name="reason" label="Reason" required placeholder="e.g. Payment dispute — awaiting call back" />
    </x-admin.modal>
@endif
