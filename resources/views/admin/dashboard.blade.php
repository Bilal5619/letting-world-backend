@php
    use App\Support\AdminUi;
    use App\Models\Certificate;
    $now = AdminUi::local();
    $hour = $now->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $series = $revenue['series'];
    $max = max(1, $series->max('value'));
    $step = 10 ** max(1, floor(log10($max)));
    $top = ceil($max / $step) * $step;
    $segments = [
        'valid' => ['Valid', 'circle-check', '--seg-valid'],
        'renewalBooked' => ['Renewal booked', 'calendar-check', '--seg-booked'],
        'expiring' => ['Expiring soon', 'clock', '--seg-expiring'],
        'missing' => ['Missing', 'shield-alert', '--seg-missing'],
        'expired' => ['Expired', 'triangle-alert', '--seg-expired'],
    ];
@endphp
<x-admin.layout title="{{ $greeting }}, {{ auth()->user()->first_name }}"
    subtitle="{{ $now->format('l j F Y') }} · {{ $attention ? count($attention).' '.str('item')->plural(count($attention)).' need'.(count($attention) === 1 ? 's' : '').' attention' : 'nothing needs attention right now' }}">
    <x-slot:actions>
        <x-admin.button href="{{ route('admin.certificates.index') }}" icon="shield-check">Compliance</x-admin.button>
        <x-admin.button variant="primary" href="{{ route('admin.bookings.index', ['tab' => 'attention']) }}" icon="calendar-check">Bookings queue</x-admin.button>
    </x-slot:actions>

    {{-- KPI row --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Landlords" :value="AdminUi::compact($stats['landlords'])" icon="user-round" href="{{ route('admin.landlords.index') }}"
            :delta="$stats['newLandlords'] ? '+'.$stats['newLandlords'] : null" delta-tone="ok" :hint="$stats['newLandlords'] ? 'joined in the last 30 days' : 'no new sign-ups in 30 days'" />
        <x-admin.stat label="Properties" :value="AdminUi::compact($stats['properties'])" icon="building-2" href="{{ route('admin.properties.index') }}"
            :hint="$stats['let'].' let · '.number_format($stats['tenants']).' tenants'" />
        <x-admin.stat label="Open bookings" :value="AdminUi::compact($stats['openBookings'])" icon="calendar-check" href="{{ route('admin.bookings.index', ['tab' => 'open']) }}"
            :hint="$stats['openRepairs'].' open repairs'" />
        <x-admin.stat label="Payments this month" :value="AdminUi::money($revenue['current'], false)" icon="pound-sterling" href="{{ route('admin.payments.index') }}"
            :delta="$revenue['delta'] !== null ? ($revenue['delta'] >= 0 ? '+' : '').$revenue['delta'].'%' : null"
            :delta-tone="$revenue['delta'] === null ? 'neutral' : ($revenue['delta'] >= 0 ? 'ok' : 'bad')"
            :hint="$revenue['delta'] !== null ? 'vs last month' : 'renewal payments taken'" />
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        {{-- Payments chart --}}
        <x-admin.card class="xl:col-span-2" title="Renewal payments" description="Paid renewal bookings by month, last 6 months">
            <x-slot:actions>
                <span class="text-right">
                    <span class="block text-[11px] font-medium tracking-wide text-muted uppercase">6-month total</span>
                    <span class="text-lg font-semibold text-ink">{{ AdminUi::money($revenue['total'], false) }}</span>
                </span>
            </x-slot:actions>
            <div class="relative" x-data="barChart">
                <div class="relative ml-12 h-56">
                    @foreach ([1, 0.5, 0] as $f)
                        <div class="absolute inset-x-0 border-t {{ $f === 0 ? 'border-line-strong' : 'border-dashed border-line' }}" style="bottom: {{ $f * 100 }}%">
                            <span class="absolute -top-2 -left-12 w-10 text-right text-[11px] text-muted lw-num">{{ AdminUi::money($top * $f, false) }}</span>
                        </div>
                    @endforeach
                    <div class="lw-chart absolute inset-0 flex items-end justify-around gap-2 px-2">
                        @foreach ($series as $i => $point)
                            @php($h = $top ? $point['value'] / $top * 100 : 0)
                            <div class="flex h-full w-full max-w-24 flex-col items-center justify-end">
                                <button type="button" class="lw-bar relative w-6 cursor-default rounded-t-[4px] bg-[var(--chart-bar)] transition focus-visible:outline-offset-4 sm:w-8"
                                        style="height: max({{ $point['value'] > 0 ? '3px' : '0px' }}, {{ $h }}%)"
                                        x-on:mouseenter="show($event, @js($point['full']), @js(AdminUi::money($point['value'])))" x-on:mouseleave="hide()"
                                        x-on:focus="show($event, @js($point['full']), @js(AdminUi::money($point['value'])))" x-on:blur="hide()"
                                        aria-label="{{ $point['full'] }}: {{ AdminUi::money($point['value']) }}"></button>
                            </div>
                        @endforeach
                    </div>
                    <template x-if="tip">
                        <div class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-ink px-2.5 py-1.5 text-xs whitespace-nowrap text-canvas shadow-lg"
                             :style="`left:${tip.x - 48}px; top:${tip.y - 8}px`">
                            <span class="block font-semibold" x-text="tip.value"></span><span class="opacity-70" x-text="tip.label"></span>
                        </div>
                    </template>
                </div>
                <div class="mt-2 ml-12 flex justify-around px-2 text-[11.5px] text-muted">
                    @foreach ($series as $point)
                        <span @class(['w-full max-w-24 text-center', 'font-semibold text-ink' => $loop->last])>{{ $point['label'] }}</span>
                    @endforeach
                </div>
                <details class="mt-3 text-xs text-muted">
                    <summary class="cursor-pointer select-none hover:text-ink">Show as table</summary>
                    <table class="mt-2 w-full max-w-sm text-left">
                        <thead><tr><th class="py-1 font-medium">Month</th><th class="py-1 text-right font-medium">Collected</th></tr></thead>
                        <tbody class="lw-num text-ink-2">
                            @foreach ($series as $point)<tr class="border-t border-line"><td class="py-1">{{ $point['full'] }}</td><td class="py-1 text-right">{{ AdminUi::money($point['value']) }}</td></tr>@endforeach
                        </tbody>
                    </table>
                </details>
            </div>
        </x-admin.card>

        {{-- Needs attention --}}
        <x-admin.card title="Needs attention" description="Things waiting on a person" flush>
            <x-slot:actions>
                @if ($attention)<span class="lw-badge tone-warn">{{ count($attention) }}</span>@endif
            </x-slot:actions>
            @forelse ($attention as $item)
                <a href="{{ $item['url'] }}" class="group flex items-start gap-3 border-b border-line px-5 py-3.5 transition last:border-0 hover:bg-raised">
                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg tone-{{ $item['tone'] }}"><x-admin.icon :name="$item['icon']" class="size-4" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-ink">{{ $item['title'] }}</span>
                        <span class="block truncate text-xs text-muted">{{ $item['subtitle'] }}</span>
                    </span>
                    <x-admin.icon name="chevron-right" class="mt-2 size-4 text-line-strong transition group-hover:translate-x-0.5 group-hover:text-accent" />
                </a>
            @empty
                <x-admin.empty icon="sparkles" title="All clear" message="No failed hand-overs, unassigned jobs, urgent repairs or expired certificates." />
            @endforelse
        </x-admin.card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        {{-- Compliance --}}
        <x-admin.card title="Portfolio compliance" description="Latest certificate of each type, all active properties">
            <div class="flex items-end gap-3">
                <p class="text-5xl leading-none font-semibold tracking-[-0.04em] text-ink">{{ $compliance['score'] !== null ? $compliance['score'].'%' : '—' }}</p>
                <p class="pb-1 text-sm text-muted">in date or<br>renewal booked</p>
            </div>
            @if ($compliance['all'])
                <div class="mt-5 flex h-3 w-full gap-[2px] overflow-hidden rounded-full bg-surface" role="img"
                     aria-label="@foreach ($segments as $key => [$label]){{ $label }} {{ $compliance['totals'][$key] }}. @endforeach">
                    @foreach ($segments as $key => [$label, $icon, $var])
                        @if ($compliance['totals'][$key])
                            <span class="h-full first:rounded-l-full last:rounded-r-full" style="width: {{ $compliance['totals'][$key] / $compliance['all'] * 100 }}%; background: var({{ $var }})" title="{{ $label }}: {{ $compliance['totals'][$key] }}"></span>
                        @endif
                    @endforeach
                </div>
                <ul class="mt-5 grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                    @foreach ($segments as $key => [$label, $icon, $var])
                        <li class="flex items-center gap-2">
                            <span class="size-2.5 shrink-0 rounded-[3px]" style="background: var({{ $var }})"></span>
                            <span class="flex-1 text-ink-2">{{ $label }}</span>
                            <span class="font-semibold text-ink lw-num">{{ $compliance['totals'][$key] }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-muted">No certificates recorded yet.</p>
            @endif
        </x-admin.card>

        {{-- Pipeline --}}
        <x-admin.card title="Booking pipeline" description="All renewal bookings by stage">
            @php($stages = [['requested', 'Requested', 'hourglass', 'info'], ['confirmed', 'Engineer booked', 'calendar-check', 'ok'], ['completed', 'Completed', 'badge-check', 'neutral'], ['cancelled', 'Cancelled', 'ban', 'neutral']])
            <ol class="space-y-2.5">
                @foreach ($stages as [$key, $label, $icon, $tone])
                    @php($n = (int) ($pipeline[$key] ?? 0))
                    @php($pct = $pipeline->sum() ? $n / $pipeline->sum() * 100 : 0)
                    <li>
                        <a href="{{ route('admin.bookings.index', ['tab' => in_array($key, ['requested', 'confirmed']) ? 'open' : $key]) }}" class="group flex items-center gap-3 rounded-xl px-1 py-1">
                            <span class="grid size-8 place-items-center rounded-lg tone-{{ $tone }}"><x-admin.icon :name="$icon" class="size-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between text-sm">
                                    <span class="font-medium text-ink group-hover:text-accent">{{ $label }}</span>
                                    <span class="font-semibold text-ink lw-num">{{ $n }}</span>
                                </span>
                                <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-[color-mix(in_oklab,var(--chart-bar)_14%,transparent)]">
                                    <span class="block h-full rounded-full bg-[var(--chart-bar)]" style="width: {{ $pct }}%"></span>
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </x-admin.card>

        {{-- Upcoming visits --}}
        <x-admin.card title="Next 7 days" description="Engineer visits coming up" flush class="lg:col-span-2 xl:col-span-1">
            @forelse ($upcoming as $b)
                @php($date = $b->scheduled_date ?? $b->preferred_date)
                <a href="{{ route('admin.bookings.show', $b) }}" class="flex items-center gap-3.5 border-b border-line px-5 py-3 transition last:border-0 hover:bg-raised">
                    <span class="flex w-11 shrink-0 flex-col items-center rounded-lg border border-line bg-raised py-1 leading-none">
                        <span class="text-[10px] font-semibold tracking-wider text-bad uppercase">{{ $date->format('M') }}</span>
                        <span class="mt-0.5 text-lg font-semibold text-ink">{{ $date->format('j') }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-ink">{{ Certificate::typeLabel($b->service_type) }}</span>
                        <span class="block truncate text-xs text-muted">{{ $b->property->shortAddress() }} · {{ $b->scheduled_slot ?? $b->time_slot ?? 'time tbc' }}</span>
                    </span>
                    <x-admin.badge :meta="AdminUi::bookingStatus($b->status)" />
                </a>
            @empty
                <x-admin.empty icon="calendar" title="Nothing booked this week" message="Visits scheduled in the next 7 days will show here." />
            @endforelse
        </x-admin.card>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-5">
        {{-- Recent bookings --}}
        <x-admin.card class="xl:col-span-3" title="Latest bookings" flush>
            <x-slot:actions><x-admin.button size="sm" variant="ghost" href="{{ route('admin.bookings.index') }}">View all<x-admin.icon name="arrow-right" class="size-3.5" /></x-admin.button></x-slot:actions>
            @if ($recentBookings->isEmpty())
                <x-admin.empty icon="calendar-check" title="No bookings yet" />
            @else
                <div class="overflow-x-auto">
                    <table class="lw-table">
                        <thead><tr><th>Booking</th><th>Property</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($recentBookings as $b)
                                <tr data-href="{{ route('admin.bookings.show', $b) }}">
                                    <td>
                                        <a href="{{ route('admin.bookings.show', $b) }}" class="lw-link">{{ Certificate::typeLabel($b->service_type) }}</a>
                                        <span class="mt-0.5 block text-xs text-muted"><span class="lw-mono">{{ $b->reference }}</span> · {{ $b->created_at->diffForHumans() }}</span>
                                    </td>
                                    <td><span class="block max-w-[220px] truncate">{{ $b->property->shortAddress() }}</span><span class="block text-xs text-muted">{{ $b->landlord?->fullName() }}</span></td>
                                    <td class="lw-num font-medium text-ink">{{ AdminUi::money($b->price) }}</td>
                                    <td><x-admin.badge :meta="AdminUi::bookingStatus($b->status)" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.card>

        {{-- Activity --}}
        <x-admin.card class="xl:col-span-2" title="Recent activity" description="Across all properties">
            @if ($activity->isEmpty())
                <x-admin.empty icon="activity" title="No activity yet" />
            @else
                <ol class="relative space-y-4 before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px before:bg-line">
                    @foreach ($activity as $event)
                        <li class="relative flex gap-3 pl-6">
                            <span class="absolute top-1.5 left-0 size-[15px] rounded-full border-[3px] border-surface bg-accent ring-1 ring-line"></span>
                            <div class="min-w-0">
                                <p class="text-sm text-ink">
                                    <span class="font-medium">{{ $event->title }}</span>
                                    @if ($event->property)<a href="{{ route('admin.properties.show', $event->property) }}" class="text-muted hover:text-accent"> · {{ $event->property->shortAddress() }}</a>@endif
                                </p>
                                <p class="text-xs text-muted">{{ $event->created_at->diffForHumans() }}@if ($event->user) · {{ $event->user->fullName() }}@endif</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-admin.card>
    </div>
</x-admin.layout>
