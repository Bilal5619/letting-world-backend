@php
    use App\Support\AdminUi;
    use App\Models\Certificate;
    $p = $property;
@endphp
<x-admin.layout :title="$p->address_line1" :crumbs="[['Properties', route('admin.properties.index')], [$p->shortAddress(), null]]"
    subtitle="{{ collect([$p->address_line2, $p->city, $p->postcode])->filter()->implode(', ') }}{{ $p->reference ? ' · '.$p->reference : '' }}">
    <x-slot:actions>
        @if ($p->isArchived())<span class="lw-badge tone-neutral h-8 px-3 text-[13px]">Archived</span>@endif
        <span class="lw-badge {{ $p->status === 'let' ? 'tone-ok' : 'tone-neutral' }} h-8 px-3 text-[13px]">{{ ucfirst($p->status) }}</span>
        <x-admin.button href="{{ route('admin.landlords.show', $p->landlord) }}" icon="user-round">{{ $p->landlord->fullName() }}</x-admin.button>
    </x-slot:actions>

    <div class="grid gap-4 xl:grid-cols-3">
        {{-- Overview --}}
        <div class="lw-card overflow-hidden xl:col-span-2">
            <div class="grid md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                <div class="relative min-h-56 bg-raised">
                    @if ($p->photo_path)
                        <img src="{{ route('admin.files', ['property', $p->id]) }}" alt="Photo of {{ $p->address_line1 }}" class="absolute inset-0 size-full object-cover">
                    @elseif ($p->latitude && $p->longitude)
                        <iframe title="Map of {{ $p->address_line1 }}" class="absolute inset-0 size-full border-0" loading="lazy"
                                src="https://www.openstreetmap.org/export/embed.html?bbox={{ $p->longitude - 0.006 }},{{ $p->latitude - 0.003 }},{{ $p->longitude + 0.006 }},{{ $p->latitude + 0.003 }}&layer=mapnik&marker={{ $p->latitude }},{{ $p->longitude }}"></iframe>
                    @else
                        <div class="absolute inset-0 grid place-items-center text-muted"><x-admin.icon name="house" class="size-10" /></div>
                    @endif
                </div>
                <div class="p-5">
                    <dl>
                        <x-admin.kv label="Type">{{ ucfirst($p->property_type) }}</x-admin.kv>
                        <x-admin.kv label="Bedrooms / bathrooms">{{ $p->bedrooms }} / {{ $p->bathrooms }}</x-admin.kv>
                        <x-admin.kv label="Furnishing">{{ ucfirst(str_replace('_', ' ', (string) $p->furnishing)) ?: '—' }}</x-admin.kv>
                        <x-admin.kv label="Gas supply">{{ $p->has_gas ? 'Yes' : 'No' }}</x-admin.kv>
                        <x-admin.kv label="Monthly rent">{{ $p->monthly_rent ? AdminUi::money($p->monthly_rent) : '—' }}</x-admin.kv>
                        <x-admin.kv label="Added">{{ AdminUi::date($p->created_at) }}</x-admin.kv>
                        @if ($p->latitude && $p->photo_path)
                            <x-admin.kv label="Map"><a class="lw-link inline-flex items-center gap-1" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat={{ $p->latitude }}&mlon={{ $p->longitude }}#map=17/{{ $p->latitude }}/{{ $p->longitude }}">Open map <x-admin.icon name="external-link" class="size-3.5" /></a></x-admin.kv>
                        @endif
                    </dl>
                    @if ($p->notes)<p class="mt-3 rounded-xl bg-raised px-3.5 py-3 text-sm whitespace-pre-line text-ink-2 ring-1 ring-line">{{ $p->notes }}</p>@endif
                </div>
            </div>
        </div>

        {{-- Compliance summary --}}
        <x-admin.card title="Compliance" icon="shield-check">
            @include('admin.partials.compliance-chips', ['c' => $compliance])
            <dl class="mt-4">
                <x-admin.kv label="In date">{{ $compliance['valid'] }}</x-admin.kv>
                <x-admin.kv label="Renewal booked">{{ $compliance['renewalBooked'] }}</x-admin.kv>
                <x-admin.kv label="Expiring soon">{{ $compliance['expiring'] }}</x-admin.kv>
                <x-admin.kv label="Expired">{{ $compliance['expired'] }}</x-admin.kv>
                <x-admin.kv label="Missing">{{ $missing ? collect($missing)->map(fn ($t) => Certificate::typeLabel($t))->implode(', ') : 'None' }}</x-admin.kv>
            </dl>
        </x-admin.card>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <x-admin.card title="Certificates" icon="shield-check" flush description="Latest of each type">
                @if ($current->isEmpty())
                    <x-admin.empty icon="shield-check" title="No certificates uploaded" />
                @else
                    <div class="overflow-x-auto">
                        <table class="lw-table">
                            <thead><tr><th>Certificate</th><th>Issued</th><th>Expires</th><th>Provider</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($current as $c)
                                    <tr>
                                        <td class="font-medium text-ink">{{ Certificate::typeLabel($c->type) }}</td>
                                        <td class="whitespace-nowrap">{{ AdminUi::date($c->issue_date) }}</td>
                                        <td class="whitespace-nowrap">{{ AdminUi::date($c->expiry_date) }}<span class="block text-xs text-muted">{{ AdminUi::days($c->daysUntilExpiry()) }}</span></td>
                                        <td>{{ $c->provider_name ?: '—' }}</td>
                                        <td><x-admin.badge :meta="AdminUi::certificateStatus($c->status())" /></td>
                                        <td class="text-right">@if ($c->file_path)<a href="{{ route('admin.files', ['certificate', $c->id]) }}" target="_blank" rel="noopener" class="lw-btn lw-btn-sm lw-btn-ghost"><x-admin.icon name="file-text" class="size-4" />View</a>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($superseded->isNotEmpty())
                        <details class="border-t border-line px-5 py-3 text-sm">
                            <summary class="cursor-pointer text-muted select-none hover:text-ink">{{ $superseded->count() }} older {{ str('certificate')->plural($superseded->count()) }}</summary>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($superseded as $c)
                                    <li class="flex items-center justify-between gap-3 text-ink-2">
                                        <span>{{ Certificate::typeLabel($c->type) }} · expired {{ AdminUi::date($c->expiry_date) }}</span>
                                        @if ($c->file_path)<a href="{{ route('admin.files', ['certificate', $c->id]) }}" target="_blank" rel="noopener" class="lw-link text-xs">View</a>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                @endif
            </x-admin.card>

            <x-admin.card title="Bookings" icon="calendar-check" flush>
                @if ($bookings->isEmpty())
                    <x-admin.empty icon="calendar-check" title="No renewal bookings" />
                @else
                    <div class="overflow-x-auto">
                        <table class="lw-table">
                            <thead><tr><th>Booking</th><th>Appointment</th><th>Fulfilment</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($bookings as $b)
                                    @php($date = $b->scheduled_date ?? $b->preferred_date)
                                    <tr data-href="{{ route('admin.bookings.show', $b) }}">
                                        <td><a href="{{ route('admin.bookings.show', $b) }}" class="lw-link">{{ Certificate::typeLabel($b->service_type) }}</a><span class="block text-xs text-muted lw-mono">{{ $b->reference }}</span></td>
                                        <td>{{ $date ? $date->format('j M Y') : 'Tenant choosing' }}</td>
                                        <td><x-admin.badge :meta="AdminUi::provider($b)" /></td>
                                        <td class="text-right lw-num">{{ AdminUi::money($b->price) }}</td>
                                        <td><x-admin.badge :meta="AdminUi::bookingStatus($b->status)" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>

            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.card title="Documents" icon="file-text" flush :description="$archivedDocuments ? $archivedDocuments.' archived' : null">
                    @forelse ($documents as $d)
                        <a href="{{ route('admin.files', ['document', $d->id]) }}" target="_blank" rel="noopener" class="flex items-center gap-3 border-b border-line px-5 py-3 transition last:border-0 hover:bg-raised">
                            <span class="grid size-8 place-items-center rounded-lg bg-raised text-muted ring-1 ring-line"><x-admin.icon name="file-text" class="size-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-ink">{{ $d->title }}</span>
                                <span class="block text-xs text-muted">{{ config("lettingworld.document_categories.{$d->category}", $d->category) }}{{ $d->shared_with_tenant ? ' · shared with tenant' : '' }}</span>
                            </span>
                            <x-admin.icon name="external-link" class="size-4 text-muted" />
                        </a>
                    @empty
                        <x-admin.empty icon="file-text" title="No documents" />
                    @endforelse
                </x-admin.card>

                <x-admin.card title="Repairs" icon="wrench" flush>
                    @forelse ($repairs as $r)
                        <a href="{{ route('admin.maintenance.show', $r) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 transition last:border-0 hover:bg-raised">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-ink">{{ $r->title }}</span>
                                <span class="block text-xs text-muted">{{ $r->created_at->format('j M Y') }}{{ $r->tenant ? ' · '.$r->tenant->fullName() : '' }}</span>
                            </span>
                            <x-admin.badge :meta="AdminUi::maintenanceStatus($r->status)" />
                        </a>
                    @empty
                        <x-admin.empty icon="wrench" title="No repairs reported" />
                    @endforelse
                </x-admin.card>
            </div>
        </div>

        <div class="space-y-4">
            <x-admin.card title="Tenants" icon="users" flush>
                @forelse ($p->tenancies as $tenancy)
                    <a href="{{ route('admin.tenants.show', $tenancy->tenant) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 transition last:border-0 hover:bg-raised">
                        <x-admin.avatar :name="$tenancy->tenant->fullName()" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-ink">{{ $tenancy->tenant->fullName() }}</span>
                            <span class="block text-xs text-muted">{{ $tenancy->start_date ? 'From '.$tenancy->start_date->format('j M Y') : 'No dates' }}{{ $tenancy->end_date ? ' to '.$tenancy->end_date->format('j M Y') : '' }}</span>
                        </span>
                    </a>
                @empty
                    <x-admin.empty icon="users" title="No tenants" />
                @endforelse
            </x-admin.card>

            <x-admin.card title="Tenancy agreements" icon="clipboard-list" flush>
                @forelse ($p->agreements as $a)
                    <div class="border-b border-line px-5 py-3 last:border-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-ink">{{ AdminUi::date($a->start_date) }} – {{ $a->end_date ? AdminUi::date($a->end_date) : 'rolling' }}</p>
                            <span class="lw-badge {{ ['active' => 'tone-ok', 'upcoming' => 'tone-info', 'ended' => 'tone-neutral'][$a->status()] }}">{{ ucfirst($a->status()) }}</span>
                        </div>
                        <p class="mt-0.5 text-xs text-muted">{{ $a->tenants->map->fullName()->implode(', ') }}{{ $a->monthly_rent ? ' · '.AdminUi::money($a->monthly_rent).'/mo' : '' }}</p>
                        @if ($a->contract)<a href="{{ route('admin.files', ['document', $a->contract->id]) }}" target="_blank" rel="noopener" class="lw-link mt-1 inline-flex items-center gap-1 text-xs">Signed contract <x-admin.icon name="external-link" class="size-3" /></a>@endif
                    </div>
                @empty
                    <x-admin.empty icon="clipboard-list" title="No agreements" />
                @endforelse
            </x-admin.card>

            <x-admin.card title="History" icon="history">
                @if ($history->isEmpty())
                    <p class="text-sm text-muted">Nothing recorded yet.</p>
                @else
                    <ol class="relative -mr-2 max-h-[560px] space-y-4 overflow-y-auto pr-2 before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px before:bg-line">
                        @foreach ($history as $e)
                            <li class="relative pl-6">
                                <span class="absolute top-1.5 left-0 size-[15px] rounded-full border-[3px] border-surface bg-accent ring-1 ring-line"></span>
                                <p class="text-sm font-medium text-ink">{{ $e->title }}</p>
                                @if ($e->description)<p class="text-xs text-ink-2">{{ $e->description }}</p>@endif
                                <p class="text-xs text-muted">{{ AdminUi::dateTime($e->created_at) }}{{ $e->user ? ' · '.$e->user->fullName() : '' }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-admin.card>
        </div>
    </div>
</x-admin.layout>
