@php
    use App\Support\AdminUi;
    use App\Models\Certificate;
@endphp
<x-admin.layout title="Certificates" subtitle="Compliance across every active property: the latest certificate of each type." :crumbs="[['Certificates', null]]">
    <x-admin.card flush>
        <x-admin.tabs :current="$tab" :counts="$counts" :tabs="[
            'expired' => 'Expired', 'expiring' => 'Expiring soon', 'missing' => 'Missing', 'renewal_booked' => 'Renewal booked',
            'valid' => 'Valid', 'no_expiry' => 'No expiry', 'all' => 'All',
        ]" />
        <x-admin.filters placeholder="Address, postcode or landlord" :keep="['tab']">
            <select name="type" class="lw-input h-9 w-auto pr-8" onchange="this.form.submit()" aria-label="Certificate type">
                <option value="">All types</option>
                @foreach (config('lettingworld.certificate_types') as $key => $meta)
                    <option value="{{ $key }}" @selected($type === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </x-admin.filters>

        @if ($tab === 'missing')
            @if ($missing->isEmpty())
                <x-admin.empty icon="shield-check" title="No missing certificates" message="Every active property has its required certificates on file." />
            @else
                <div class="overflow-x-auto">
                    <table class="lw-table">
                        <thead><tr><th>Property</th><th>Landlord</th><th>Missing</th></tr></thead>
                        <tbody>
                            @foreach ($missing as $row)
                                <tr data-href="{{ route('admin.properties.show', $row['property']) }}">
                                    <td><a href="{{ route('admin.properties.show', $row['property']) }}" class="lw-link">{{ $row['property']->shortAddress() }}</a><span class="block text-xs text-muted">{{ $row['property']->postcode }}</span></td>
                                    <td>{{ $row['property']->landlord?->fullName() }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($row['types'] as $t)
                                                <span class="lw-badge tone-violet"><x-admin.icon name="shield-alert" class="size-3.5" />{{ Certificate::typeLabel($t) }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @elseif ($certificates->isEmpty())
            <x-admin.empty icon="shield-check" title="Nothing here" :message="$tab === 'expired' ? 'No expired certificates without a renewal booked.' : 'No certificates match this filter.'" />
        @else
            <div class="overflow-x-auto">
                <table class="lw-table">
                    <thead><tr><th>Certificate</th><th>Property</th><th>Landlord</th><th>Expires</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($certificates as $c)
                            <tr data-href="{{ route('admin.properties.show', $c->property) }}">
                                <td class="font-medium text-ink">{{ Certificate::typeLabel($c->type) }}<span class="block text-xs font-normal text-muted">{{ $c->provider_name ?: 'Provider not recorded' }}</span></td>
                                <td><a href="{{ route('admin.properties.show', $c->property) }}" class="lw-link">{{ $c->property->shortAddress() }}</a><span class="block text-xs text-muted">{{ $c->property->postcode }}</span></td>
                                <td>@if ($c->property->landlord)<a href="{{ route('admin.landlords.show', $c->property->landlord) }}" class="hover:text-accent">{{ $c->property->landlord->fullName() }}</a>@endif</td>
                                <td class="whitespace-nowrap">{{ AdminUi::date($c->expiry_date) }}<span @class(['block text-xs', 'text-bad' => $c->daysUntilExpiry() !== null && $c->daysUntilExpiry() < 0, 'text-muted' => $c->daysUntilExpiry() === null || $c->daysUntilExpiry() >= 0])>{{ AdminUi::days($c->daysUntilExpiry()) }}</span></td>
                                <td><x-admin.badge :meta="AdminUi::certificateStatus($c->status())" /></td>
                                <td class="text-right">@if ($c->file_path)<a href="{{ route('admin.files', ['certificate', $c->id]) }}" target="_blank" rel="noopener" class="lw-btn lw-btn-sm lw-btn-ghost"><x-admin.icon name="file-text" class="size-4" />View</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $certificates->links() }}
        @endif
    </x-admin.card>
</x-admin.layout>
