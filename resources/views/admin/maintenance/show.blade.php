@php use App\Support\AdminUi; use App\Models\MaintenanceRequest; $r = $request; @endphp
<x-admin.layout :title="$r->title" :crumbs="[['Maintenance', route('admin.maintenance.index')], [$r->title, null]]"
    subtitle="{{ MaintenanceRequest::categoryLabel($r->category) }} · reported {{ AdminUi::dateTime($r->created_at) }}">
    <x-slot:actions>
        <x-admin.badge :meta="AdminUi::priority($r->priority)" class="h-8 px-3 text-[13px]" />
        <x-admin.badge :meta="AdminUi::maintenanceStatus($r->status)" class="h-8 px-3 text-[13px]" />
    </x-slot:actions>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <x-admin.card title="Details" icon="wrench">
                <p class="text-sm leading-relaxed whitespace-pre-line text-ink-2">{{ $r->description ?: 'No description given.' }}</p>
                @if ($r->photos->isNotEmpty())
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($r->photos as $photo)
                            <a href="{{ route('admin.files', ['maintenance-photo', $photo->id]) }}" target="_blank" rel="noopener" class="group relative aspect-square overflow-hidden rounded-xl ring-1 ring-line">
                                <img src="{{ route('admin.files', ['maintenance-photo', $photo->id]) }}" alt="Repair photo {{ $loop->iteration }}" class="size-full object-cover transition group-hover:scale-105" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-admin.card>

            <x-admin.card title="Updates" icon="history" description="Conversation between landlord and tenant">
                @if ($r->updates->isEmpty())
                    <p class="text-sm text-muted">No updates yet.</p>
                @else
                    <ol class="space-y-4">
                        @foreach ($r->updates as $u)
                            <li class="flex gap-3">
                                <x-admin.avatar :name="$u->user?->fullName() ?? 'System'" size="sm" />
                                <div class="min-w-0 flex-1 rounded-xl bg-raised px-3.5 py-2.5 ring-1 ring-line">
                                    <p class="text-xs text-muted"><span class="font-medium text-ink">{{ $u->user?->fullName() ?? 'System' }}</span> · {{ AdminUi::dateTime($u->created_at) }}</p>
                                    @if ($u->status)<p class="mt-1"><x-admin.badge :meta="AdminUi::maintenanceStatus($u->status)" /></p>@endif
                                    @if ($u->note)<p class="mt-1 text-sm whitespace-pre-line text-ink-2">{{ $u->note }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-admin.card>
        </div>

        <x-admin.card title="Property" icon="building-2">
            <a href="{{ route('admin.properties.show', $r->property) }}" class="lw-link">{{ $r->property->shortAddress() }}</a>
            <p class="text-sm text-muted">{{ $r->property->postcode }}</p>
            <dl class="mt-3">
                <x-admin.kv label="Landlord"><a href="{{ route('admin.landlords.show', $r->property->landlord) }}" class="lw-link">{{ $r->property->landlord->fullName() }}</a></x-admin.kv>
                <x-admin.kv label="Landlord phone">{{ $r->property->landlord->phone ?: '—' }}</x-admin.kv>
                <x-admin.kv label="Tenant">@if ($r->tenant)<a href="{{ route('admin.tenants.show', $r->tenant) }}" class="lw-link">{{ $r->tenant->fullName() }}</a>@else — @endif</x-admin.kv>
                <x-admin.kv label="Tenant phone">{{ $r->tenant?->phone ?: '—' }}</x-admin.kv>
                <x-admin.kv label="Reported by">{{ $r->reporter?->fullName() ?? '—' }}</x-admin.kv>
                <x-admin.kv label="Resolved">{{ $r->resolved_at ? AdminUi::dateTime($r->resolved_at) : '—' }}</x-admin.kv>
            </dl>
        </x-admin.card>
    </div>
</x-admin.layout>
