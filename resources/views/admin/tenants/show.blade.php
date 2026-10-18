@php use App\Support\AdminUi; @endphp
<x-admin.layout :title="$tenant->fullName()" :crumbs="[['Tenants', route('admin.tenants.index')], [$tenant->fullName(), null]]"
    subtitle="Tenant of {{ $tenant->landlord?->fullName() ?? 'an unknown landlord' }} · {{ $tenant->last_login_at ? 'last seen '.$tenant->last_login_at->diffForHumans() : 'never signed in' }}">
    <x-slot:actions>
        <x-admin.badge :meta="AdminUi::accountStatus($tenant)" class="h-8 px-3 text-[13px]" />
        @if ($tenant->email)<x-admin.button href="mailto:{{ $tenant->email }}" icon="mail">Email</x-admin.button>@endif
        @include('admin.partials.account-actions', ['user' => $tenant, 'suspendRoute' => route('admin.tenants.suspend', $tenant), 'reactivateRoute' => route('admin.tenants.reactivate', $tenant)])
    </x-slot:actions>

    @if ($tenant->isSuspended())
        <div class="mb-4 flex items-start gap-3 rounded-xl border border-bad/25 bg-bad-soft px-4 py-3 text-sm text-bad">
            <x-admin.icon name="ban" class="mt-0.5 size-4 shrink-0" />
            <div><p class="font-medium">Suspended {{ AdminUi::dateTime($tenant->suspended_at) }}</p><p>{{ $tenant->suspended_reason }}</p></div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        <x-admin.card title="Contact" icon="user-round">
            <div class="mb-4 flex items-center gap-3">
                <x-admin.avatar :name="$tenant->fullName()" size="lg" />
                <div><p class="font-semibold text-ink">{{ $tenant->fullName() }}</p><p class="text-sm text-muted">Tenant account</p></div>
            </div>
            <dl>
                <x-admin.kv label="Email">{{ $tenant->email ?: '—' }}</x-admin.kv>
                <x-admin.kv label="Phone">{{ $tenant->phone ?: '—' }}</x-admin.kv>
                <x-admin.kv label="Emergency contact">{{ $tenant->emergency_contact_name ? $tenant->emergency_contact_name.' · '.$tenant->emergency_contact_phone : '—' }}</x-admin.kv>
                <x-admin.kv label="Landlord">@if ($tenant->landlord)<a href="{{ route('admin.landlords.show', $tenant->landlord) }}" class="lw-link">{{ $tenant->landlord->fullName() }}</a>@else — @endif</x-admin.kv>
                <x-admin.kv label="Password">{{ $tenant->must_change_password ? 'Still using the temporary one' : 'Set by tenant' }}</x-admin.kv>
                <x-admin.kv label="Account created">{{ AdminUi::date($tenant->created_at) }}</x-admin.kv>
            </dl>
        </x-admin.card>

        <div class="space-y-4 xl:col-span-2">
            <x-admin.card title="Tenancies" icon="house" flush>
                @if ($tenant->tenancies->isEmpty())
                    <x-admin.empty icon="house" title="Not assigned to a property" />
                @else
                    <div class="overflow-x-auto">
                        <table class="lw-table">
                            <thead><tr><th>Property</th><th>Start</th><th>End</th><th class="text-right">Rent</th><th class="text-right">Deposit</th></tr></thead>
                            <tbody>
                                @foreach ($tenant->tenancies as $tenancy)
                                    <tr data-href="{{ route('admin.properties.show', $tenancy->property) }}">
                                        <td><a href="{{ route('admin.properties.show', $tenancy->property) }}" class="lw-link">{{ $tenancy->property->shortAddress() }}</a><span class="block text-xs text-muted">{{ $tenancy->property->postcode }}</span></td>
                                        <td>{{ AdminUi::date($tenancy->start_date) }}</td>
                                        <td>{{ $tenancy->end_date ? AdminUi::date($tenancy->end_date) : 'Rolling' }}</td>
                                        <td class="text-right lw-num">{{ $tenancy->monthly_rent ? AdminUi::money($tenancy->monthly_rent).'/mo' : '—' }}</td>
                                        <td class="text-right lw-num">{{ AdminUi::money($tenancy->deposit_amount) }}<span class="block text-xs text-muted">{{ $tenancy->deposit_scheme }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>

            <x-admin.card title="Repairs reported" icon="wrench" flush>
                @if ($repairs->isEmpty())
                    <x-admin.empty icon="wrench" title="No repairs reported" />
                @else
                    <div class="overflow-x-auto">
                        <table class="lw-table">
                            <thead><tr><th>Repair</th><th>Property</th><th>Priority</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($repairs as $r)
                                    <tr data-href="{{ route('admin.maintenance.show', $r) }}">
                                        <td><a href="{{ route('admin.maintenance.show', $r) }}" class="lw-link">{{ $r->title }}</a><span class="block text-xs text-muted">{{ $r->created_at->format('j M Y') }}</span></td>
                                        <td>{{ $r->property->shortAddress() }}</td>
                                        <td><x-admin.badge :meta="AdminUi::priority($r->priority)" /></td>
                                        <td><x-admin.badge :meta="AdminUi::maintenanceStatus($r->status)" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>

            @include('admin.partials.admin-log', ['actions' => $actions])
        </div>
    </div>
</x-admin.layout>
