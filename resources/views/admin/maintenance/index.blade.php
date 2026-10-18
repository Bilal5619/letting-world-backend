@php use App\Support\AdminUi; use App\Models\MaintenanceRequest; @endphp
<x-admin.layout title="Maintenance" subtitle="Repairs reported by tenants and landlords. Landlords handle them in the app; this view is for oversight." :crumbs="[['Maintenance', null]]">
    <x-admin.card flush>
        <x-admin.tabs param="view" :current="$view" :counts="$counts" :tabs="['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All']" />
        <x-admin.filters placeholder="Repair, address or postcode" :keep="['view']">
            <select name="priority" class="lw-input h-9 w-auto pr-8" onchange="this.form.submit()" aria-label="Priority">
                <option value="">Any priority</option>
                @foreach (['emergency' => 'Emergency', 'urgent' => 'Urgent', 'normal' => 'Normal', 'low' => 'Low'] as $k => $v)
                    <option value="{{ $k }}" @selected($priority === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </x-admin.filters>

        @if ($requests->isEmpty())
            <x-admin.empty icon="wrench" title="No repairs here" message="Repairs matching this filter will show here." />
        @else
            <div class="overflow-x-auto">
                <table class="lw-table">
                    <thead><tr><th>Repair</th><th>Property</th><th>Reported by</th><th>Priority</th><th>Status</th><th>Age</th></tr></thead>
                    <tbody>
                        @foreach ($requests as $r)
                            <tr data-href="{{ route('admin.maintenance.show', $r) }}">
                                <td>
                                    <a href="{{ route('admin.maintenance.show', $r) }}" class="lw-link">{{ $r->title }}</a>
                                    <span class="block text-xs text-muted">{{ MaintenanceRequest::categoryLabel($r->category) }}{{ $r->photos_count ? ' · '.$r->photos_count.' '.str('photo')->plural($r->photos_count) : '' }}</span>
                                </td>
                                <td><span class="block max-w-[240px] truncate">{{ $r->property->shortAddress() }}</span><span class="block text-xs text-muted">{{ $r->property->landlord?->fullName() }}</span></td>
                                <td>{{ $r->reporter?->fullName() ?? '—' }}<span class="block text-xs text-muted">{{ $r->reporter?->isTenant() ? 'Tenant' : 'Landlord' }}</span></td>
                                <td><x-admin.badge :meta="AdminUi::priority($r->priority)" /></td>
                                <td><x-admin.badge :meta="AdminUi::maintenanceStatus($r->status)" /></td>
                                <td class="whitespace-nowrap text-xs">{{ $r->created_at->diffForHumans(null, true) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $requests->links() }}
        @endif
    </x-admin.card>
</x-admin.layout>
