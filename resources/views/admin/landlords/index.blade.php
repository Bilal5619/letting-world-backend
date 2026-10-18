@php use App\Support\AdminUi; @endphp
<x-admin.layout title="Landlords" subtitle="{{ number_format($total) }} landlord {{ str('account')->plural($total) }} registered in the app." :crumbs="[['Landlords', null]]">
    <x-admin.card flush>
        <x-admin.tabs param="state" :current="request('state', '')" :tabs="['' => 'All', 'active' => 'Active', 'incomplete' => 'Profile incomplete', 'suspended' => 'Suspended']" />
        <x-admin.filters placeholder="Name, email, phone or company" :keep="['state']" />

        @if ($landlords->isEmpty())
            <x-admin.empty icon="user-round" title="No landlords found" message="Try a different search or filter." />
        @else
            <div class="overflow-x-auto">
                <table class="lw-table">
                    <thead><tr><th>Landlord</th><th>Type</th><th class="text-right">Properties</th><th class="text-right">Tenants</th><th class="text-right">Bookings</th><th class="text-right">Paid</th><th>Joined</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($landlords as $l)
                            <tr data-href="{{ route('admin.landlords.show', $l) }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-admin.avatar :name="$l->fullName()" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.landlords.show', $l) }}" class="lw-link">{{ $l->fullName() }}</a>
                                            <span class="block truncate text-xs text-muted">{{ $l->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ $l->landlordProfile?->landlord_type === 'company' ? ($l->landlordProfile->company_name ?: 'Company') : 'Individual' }}
                                    @if ($l->landlordProfile?->city)<span class="block text-xs text-muted">{{ $l->landlordProfile->city }}</span>@endif
                                </td>
                                <td class="text-right lw-num text-ink">{{ $l->properties_count }}</td>
                                <td class="text-right lw-num">{{ $l->tenants_count }}</td>
                                <td class="text-right lw-num">{{ $l->bookings_count }}</td>
                                <td class="text-right lw-num font-medium text-ink">{{ AdminUi::money((float) $l->paid_total, false) }}</td>
                                <td class="whitespace-nowrap">{{ $l->created_at->format('j M Y') }}<span class="block text-xs text-muted">{{ $l->last_login_at ? 'seen '.$l->last_login_at->diffForHumans() : 'never signed in' }}</span></td>
                                <td><x-admin.badge :meta="AdminUi::accountStatus($l)" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $landlords->links() }}
        @endif
    </x-admin.card>
</x-admin.layout>
