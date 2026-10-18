@php
    use App\Support\AdminUi;
    use App\Models\Certificate;
    $p = $landlord->landlordProfile;
@endphp
<x-admin.layout :title="$landlord->fullName()" :crumbs="[['Landlords', route('admin.landlords.index')], [$landlord->fullName(), null]]"
    subtitle="Landlord since {{ $landlord->created_at->format('F Y') }} · {{ $landlord->last_login_at ? 'last seen '.$landlord->last_login_at->diffForHumans() : 'never signed in' }}">
    <x-slot:actions>
        <x-admin.badge :meta="AdminUi::accountStatus($landlord)" class="h-8 px-3 text-[13px]" />
        <x-admin.button href="mailto:{{ $landlord->email }}" icon="mail">Email</x-admin.button>
        @include('admin.partials.account-actions', ['user' => $landlord, 'suspendRoute' => route('admin.landlords.suspend', $landlord), 'reactivateRoute' => route('admin.landlords.reactivate', $landlord)])
    </x-slot:actions>

    @if ($landlord->isSuspended())
        <div class="mb-4 flex items-start gap-3 rounded-xl border border-bad/25 bg-bad-soft px-4 py-3 text-sm text-bad">
            <x-admin.icon name="ban" class="mt-0.5 size-4 shrink-0" />
            <div><p class="font-medium">Suspended {{ AdminUi::dateTime($landlord->suspended_at) }}</p><p>{{ $landlord->suspended_reason }}</p></div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Properties" :value="$properties->whereNull('archived_at')->count()" icon="building-2" :hint="$properties->whereNotNull('archived_at')->count().' archived'" />
        <x-admin.stat label="Tenants" :value="$tenants->count()" icon="users" />
        <x-admin.stat label="Open bookings" :value="$openBookings" icon="calendar-check" />
        <x-admin.stat label="Paid for renewals" :value="AdminUi::money($paid, false)" icon="pound-sterling" hint="all time" />
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="space-y-4">
            <x-admin.card title="Profile" icon="user-round">
                <div class="mb-4 flex items-center gap-3">
                    <x-admin.avatar :name="$landlord->fullName()" size="lg" />
                    <div class="min-w-0">
                        <p class="font-semibold text-ink">{{ $landlord->fullName() }}</p>
                        <p class="truncate text-sm text-muted">{{ $p?->landlord_type === 'company' ? ($p->company_name ?: 'Company landlord') : 'Individual landlord' }}</p>
                    </div>
                </div>
                <dl>
                    <x-admin.kv label="Email"><a href="mailto:{{ $landlord->email }}" class="lw-link">{{ $landlord->email }}</a></x-admin.kv>
                    <x-admin.kv label="Phone">{{ $landlord->phone ?: '—' }}</x-admin.kv>
                    <x-admin.kv label="WhatsApp">{{ $p?->whatsapp ?: '—' }}</x-admin.kv>
                    <x-admin.kv label="Address">{{ $p ? collect([$p->address_line1, $p->address_line2, $p->city, $p->postcode])->filter()->implode(', ') : '—' }}</x-admin.kv>
                    @if ($p?->company_number)<x-admin.kv label="Company no.">{{ $p->company_number }}</x-admin.kv>@endif
                    @if ($p?->registration_number)<x-admin.kv label="Landlord reg.">{{ $p->registration_number }}</x-admin.kv>@endif
                    <x-admin.kv label="Experience">{{ $p?->experience ? ucfirst(str_replace('_', ' ', $p->experience)) : '—' }}</x-admin.kv>
                    <x-admin.kv label="Prefers">{{ $p?->preferred_contact ? ucfirst($p->preferred_contact) : '—' }}</x-admin.kv>
                    <x-admin.kv label="Sign-in">{{ $landlord->auth_provider === 'google' ? 'Google' : 'Email & password' }}</x-admin.kv>
                    <x-admin.kv label="Card on file">{{ $landlord->stripe_customer_id ? 'Yes (Stripe)' : 'No' }}</x-admin.kv>
                    <x-admin.kv label="Marketing">{{ $p?->marketing_consent ? 'Opted in' : 'Opted out' }}</x-admin.kv>
                </dl>
            </x-admin.card>

            <x-admin.card title="Tenants" icon="users" flush>
                @forelse ($tenants as $t)
                    <a href="{{ route('admin.tenants.show', $t) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 transition last:border-0 hover:bg-raised">
                        <x-admin.avatar :name="$t->fullName()" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-ink">{{ $t->fullName() }}</span>
                            <span class="block truncate text-xs text-muted">{{ $t->assignedProperties->map->shortAddress()->implode(' · ') ?: 'Not assigned to a property' }}</span>
                        </span>
                        <x-admin.badge :meta="AdminUi::accountStatus($t)" />
                    </a>
                @empty
                    <x-admin.empty icon="users" title="No tenants yet" />
                @endforelse
            </x-admin.card>
        </div>

        <div class="space-y-4 xl:col-span-2">
            <x-admin.card title="Properties" icon="building-2" flush>
                @if ($properties->isEmpty())
                    <x-admin.empty icon="building-2" title="No properties yet" />
                @else
                    <div class="overflow-x-auto">
                        <table class="lw-table">
                            <thead><tr><th>Property</th><th class="text-right">Tenants</th><th>Compliance</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($properties as $prop)
                                    <tr data-href="{{ route('admin.properties.show', $prop) }}">
                                        <td><a href="{{ route('admin.properties.show', $prop) }}" class="lw-link">{{ $prop->shortAddress() }}</a><span class="block text-xs text-muted">{{ $prop->postcode }} · {{ ucfirst($prop->property_type) }}, {{ $prop->bedrooms }} bed</span></td>
                                        <td class="text-right lw-num">{{ $prop->tenants_count }}</td>
                                        <td>@include('admin.partials.compliance-chips', ['c' => $prop->compliance])</td>
                                        <td>
                                            @if ($prop->isArchived())<span class="lw-badge tone-neutral">Archived</span>
                                            @else<span class="lw-badge {{ $prop->status === 'let' ? 'tone-ok' : 'tone-neutral' }}">{{ ucfirst($prop->status) }}</span>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>

            <x-admin.card title="Recent bookings" icon="calendar-check" flush>
                <x-slot:actions><x-admin.button size="sm" variant="ghost" href="{{ route('admin.bookings.index', ['q' => $landlord->email]) }}">All bookings</x-admin.button></x-slot:actions>
                @if ($bookings->isEmpty())
                    <x-admin.empty icon="calendar-check" title="No bookings yet" />
                @else
                    <div class="overflow-x-auto">
                        <table class="lw-table">
                            <thead><tr><th>Booking</th><th>Property</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($bookings as $b)
                                    <tr data-href="{{ route('admin.bookings.show', $b) }}">
                                        <td><a href="{{ route('admin.bookings.show', $b) }}" class="lw-link">{{ Certificate::typeLabel($b->service_type) }}</a><span class="block text-xs text-muted"><span class="lw-mono">{{ $b->reference }}</span> · {{ $b->created_at->format('j M Y') }}</span></td>
                                        <td>{{ $b->property->shortAddress() }}</td>
                                        <td class="text-right lw-num font-medium text-ink">{{ AdminUi::money($b->price) }}</td>
                                        <td><x-admin.badge :meta="AdminUi::bookingStatus($b->status)" /></td>
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
