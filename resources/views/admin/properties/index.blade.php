@php use App\Support\AdminUi; @endphp
<x-admin.layout title="Properties" subtitle="Every property landlords manage in Letting World, with live compliance." :crumbs="[['Properties', null]]">
    <x-admin.card flush>
        <x-admin.tabs param="view" :current="$archived ? 'archived' : ''" :counts="['' => $counts['active'], 'archived' => $counts['archived']]" :tabs="['' => 'Active', 'archived' => 'Archived']" />
        <x-admin.filters placeholder="Address, postcode, reference or landlord" :keep="['view']">
            <select name="status" class="lw-input h-9 w-auto pr-8" onchange="this.form.submit()" aria-label="Occupancy">
                <option value="">Let & vacant</option>
                <option value="let" @selected(request('status') === 'let')>Let</option>
                <option value="vacant" @selected(request('status') === 'vacant')>Vacant</option>
            </select>
        </x-admin.filters>

        @if ($properties->isEmpty())
            <x-admin.empty icon="building-2" title="No properties found" message="Try a different search or filter." />
        @else
            <div class="overflow-x-auto">
                <table class="lw-table">
                    <thead><tr><th>Property</th><th>Landlord</th><th>Details</th><th class="text-right">Tenants</th><th class="text-right">Rent</th><th>Compliance</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($properties as $prop)
                            <tr data-href="{{ route('admin.properties.show', $prop) }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        @if ($prop->photo_path)
                                            <img src="{{ route('admin.files', ['property', $prop->id]) }}" alt="" class="size-10 shrink-0 rounded-lg object-cover ring-1 ring-line" loading="lazy">
                                        @else
                                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-raised text-muted ring-1 ring-line"><x-admin.icon name="house" class="size-4" /></span>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.properties.show', $prop) }}" class="lw-link">{{ $prop->address_line1 }}</a>
                                            <span class="block text-xs text-muted">{{ $prop->city }} · {{ $prop->postcode }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>@if ($prop->landlord)<a href="{{ route('admin.landlords.show', $prop->landlord) }}" class="hover:text-accent">{{ $prop->landlord->fullName() }}</a>@endif</td>
                                <td class="whitespace-nowrap">{{ ucfirst($prop->property_type) }}<span class="block text-xs text-muted">{{ $prop->bedrooms }} bed · {{ $prop->bathrooms }} bath{{ $prop->has_gas ? ' · gas' : '' }}</span></td>
                                <td class="text-right lw-num">{{ $prop->tenants_count }}</td>
                                <td class="text-right lw-num">{{ $prop->monthly_rent ? AdminUi::money($prop->monthly_rent, false) : '—' }}</td>
                                <td>@include('admin.partials.compliance-chips', ['c' => $prop->compliance])</td>
                                <td><span class="lw-badge {{ $prop->status === 'let' ? 'tone-ok' : 'tone-neutral' }}">{{ ucfirst($prop->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $properties->links() }}
        @endif
    </x-admin.card>
</x-admin.layout>
