@php
    use App\Support\AdminUi;
    use App\Models\Certificate;
@endphp
<x-admin.layout title="Bookings" subtitle="Every certificate renewal: paid, handed to 11Done or arranged by the team, through to the new certificate." :crumbs="[['Bookings', null]]">
    <x-admin.card flush>
        <x-admin.tabs :current="$tab" :counts="$counts" :tabs="['all' => 'All', 'attention' => 'Needs action', 'open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled']" />
        <x-admin.filters placeholder="Reference, 11Done order, address or landlord" :keep="['tab']">
            <select name="provider" class="lw-input h-9 w-auto pr-8" onchange="this.form.submit()" aria-label="Fulfilled by">
                <option value="">Any provider</option>
                <option value="11done" @selected(request('provider') === '11done')>11Done</option>
                <option value="team" @selected(request('provider') === 'team')>Team arranged</option>
            </select>
            <select name="service" class="lw-input h-9 w-auto pr-8" onchange="this.form.submit()" aria-label="Service">
                <option value="">Any service</option>
                @foreach ($services as $service)
                    <option value="{{ $service }}" @selected(request('service') === $service)>{{ Certificate::typeLabel($service) }}</option>
                @endforeach
            </select>
        </x-admin.filters>

        @if ($bookings->isEmpty())
            <x-admin.empty icon="calendar-check" :title="$tab === 'attention' ? 'Nothing needs action' : 'No bookings found'"
                :message="$tab === 'attention' ? 'Failed hand-overs to 11Done and team-arranged jobs waiting for a contractor show up here.' : 'Try a different filter or search.'" />
        @else
            <div class="overflow-x-auto">
                <table class="lw-table">
                    <thead>
                        <tr><th>Booking</th><th>Property & landlord</th><th>Appointment</th><th>Fulfilment</th><th class="text-right">Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $b)
                            @php($sync = AdminUi::syncStatus($b))
                            @php($date = $b->scheduled_date ?? $b->preferred_date)
                            <tr data-href="{{ route('admin.bookings.show', $b) }}">
                                <td>
                                    <a href="{{ route('admin.bookings.show', $b) }}" class="lw-link">{{ Certificate::typeLabel($b->service_type) }}</a>
                                    <span class="mt-0.5 block text-xs text-muted"><span class="lw-mono">{{ $b->reference }}</span> · {{ $b->created_at->format('j M Y') }}</span>
                                </td>
                                <td>
                                    <span class="block max-w-[240px] truncate text-ink">{{ $b->property->shortAddress() }}</span>
                                    <span class="block text-xs text-muted">{{ $b->landlord?->fullName() }}</span>
                                </td>
                                <td>
                                    @if ($date)
                                        <span class="block text-ink">{{ $date->format('D j M') }}</span>
                                        <span class="block text-xs text-muted">{{ $b->scheduled_slot ?? $b->time_slot }}</span>
                                    @else
                                        <span class="text-xs text-muted">Tenant choosing</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-col items-start gap-1">
                                        <x-admin.badge :meta="AdminUi::provider($b)" />
                                        @if ($sync && $b->sync_status !== 'sent')<x-admin.badge :meta="$sync" />@endif
                                        @if ($b->provider_reference)<span class="lw-mono text-xs text-muted">{{ $b->provider_reference }}</span>@endif
                                    </div>
                                </td>
                                <td class="text-right">
                                    <span class="block font-medium text-ink lw-num">{{ AdminUi::money($b->price) }}</span>
                                    <span class="text-xs text-muted">{{ AdminUi::paymentStatus($b->payment_status)['label'] }}</span>
                                </td>
                                <td><x-admin.badge :meta="AdminUi::bookingStatus($b->status)" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $bookings->links() }}
        @endif
    </x-admin.card>
</x-admin.layout>
