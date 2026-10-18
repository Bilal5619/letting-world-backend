@php use App\Support\AdminUi; @endphp
<x-admin.layout title="Tenants" subtitle="{{ number_format($total) }} tenant {{ str('account')->plural($total) }}, each created by their landlord." :crumbs="[['Tenants', null]]">
    <x-admin.card flush>
        <x-admin.tabs param="state" :current="request('state', '')" :tabs="['' => 'All', 'active' => 'Active', 'invited' => 'Invited', 'suspended' => 'Suspended']" />
        <x-admin.filters placeholder="Name, email, phone or property" :keep="['state']" />

        @if ($tenants->isEmpty())
            <x-admin.empty icon="users" title="No tenants found" message="Try a different search or filter." />
        @else
            <div class="overflow-x-auto">
                <table class="lw-table">
                    <thead><tr><th>Tenant</th><th>Property</th><th>Landlord</th><th>Phone</th><th>Last seen</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($tenants as $t)
                            <tr data-href="{{ route('admin.tenants.show', $t) }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-admin.avatar :name="$t->fullName()" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.tenants.show', $t) }}" class="lw-link">{{ $t->fullName() }}</a>
                                            <span class="block truncate text-xs text-muted">{{ $t->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @forelse ($t->assignedProperties as $prop)
                                        <span class="block max-w-[240px] truncate">{{ $prop->shortAddress() }}</span>
                                    @empty
                                        <span class="text-xs text-muted">Not assigned</span>
                                    @endforelse
                                </td>
                                <td>@if ($t->landlord)<a href="{{ route('admin.landlords.show', $t->landlord) }}" class="hover:text-accent">{{ $t->landlord->fullName() }}</a>@endif</td>
                                <td class="whitespace-nowrap">{{ $t->phone ?: '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $t->last_login_at ? $t->last_login_at->diffForHumans() : 'Never' }}</td>
                                <td><x-admin.badge :meta="AdminUi::accountStatus($t)" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $tenants->links() }}
        @endif
    </x-admin.card>
</x-admin.layout>
