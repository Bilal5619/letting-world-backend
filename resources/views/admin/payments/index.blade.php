@php use App\Support\AdminUi; use App\Models\Certificate; @endphp
<x-admin.layout title="Payments" subtitle="Renewal payments taken from landlords’ saved cards through Stripe." :crumbs="[['Payments', null]]">
    <x-slot:actions>
        <x-admin.button href="{{ route('admin.payments.export', request()->only('status', 'q')) }}" icon="download">Export CSV</x-admin.button>
    </x-slot:actions>

    <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Collected" :value="AdminUi::money((float) $totals->collected, false)" icon="pound-sterling" :hint="(int) $totals->paid_count.' paid bookings'" />
        <x-admin.stat label="Refunded" :value="AdminUi::money((float) $totals->refunded, false)" icon="rotate-ccw" :hint="(int) $totals->refunded_count.' refunds'" />
        <x-admin.stat label="Average booking" :value="$totals->paid_count ? AdminUi::money((float) $totals->collected / (int) $totals->paid_count) : '—'" icon="banknote" hint="per paid renewal" />
        <x-admin.stat label="This month" :value="AdminUi::money((float) $totals->this_month, false)" icon="trending-up" :hint="now()->format('F Y')" />
    </div>

    <x-admin.card flush>
        <x-admin.tabs param="status" :current="$status ?? ''" :tabs="['' => 'All', 'paid' => 'Paid', 'refunded' => 'Refunded', 'failed' => 'Failed', 'not_charged' => 'Not charged']" />
        <x-admin.filters placeholder="Booking reference, Stripe payment or landlord" :keep="['status']" />

        @if ($payments->isEmpty())
            <x-admin.empty icon="credit-card" title="No payments found" />
        @else
            <div class="overflow-x-auto">
                <table class="lw-table">
                    <thead><tr><th>Date</th><th>Booking</th><th>Landlord</th><th>Card</th><th>Stripe payment</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($payments as $b)
                            <tr data-href="{{ route('admin.bookings.show', $b) }}">
                                <td class="whitespace-nowrap">{{ AdminUi::local($b->created_at)->format('j M Y') }}<span class="block text-xs text-muted">{{ AdminUi::local($b->created_at)->format('H:i') }}</span></td>
                                <td><a href="{{ route('admin.bookings.show', $b) }}" class="lw-link lw-mono">{{ $b->reference }}</a><span class="block text-xs text-muted">{{ Certificate::typeLabel($b->service_type) }}</span></td>
                                <td>{{ $b->landlord?->fullName() }}<span class="block max-w-[200px] truncate text-xs text-muted">{{ $b->property?->shortAddress() }}</span></td>
                                <td class="whitespace-nowrap">{{ $b->card_last4 ? strtoupper($b->card_brand).' •••• '.$b->card_last4 : '—' }}</td>
                                <td><span class="lw-mono text-xs">{{ $b->payment_intent_id ?: '—' }}</span></td>
                                <td @class(['text-right lw-num font-medium', 'text-ink' => $b->payment_status === 'paid', 'text-muted line-through' => $b->payment_status === 'refunded'])>{{ AdminUi::money($b->price) }}</td>
                                <td><x-admin.badge :meta="AdminUi::paymentStatus($b->payment_status)" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $payments->links() }}
        @endif
    </x-admin.card>
</x-admin.layout>
