{{-- Filter tabs with counts; keeps the rest of the query string. --}}
@props(['tabs', 'current', 'counts' => [], 'param' => 'tab'])
<nav {{ $attributes->class(['flex gap-1 overflow-x-auto border-b border-line px-3']) }} aria-label="Filter">
    @foreach ($tabs as $key => $label)
        @php($on = (string) $current === (string) $key)
        <a href="{{ request()->fullUrlWithQuery([$param => $key, 'page' => null]) }}" @if ($on) aria-current="page" @endif
           @class(['relative -mb-px flex items-center gap-2 border-b-2 px-3 py-3 text-[13.5px] font-medium whitespace-nowrap transition',
                   'border-accent text-ink' => $on, 'border-transparent text-muted hover:text-ink' => ! $on])>
            {{ $label }}
            @isset($counts[$key])
                <span @class(['rounded-full px-1.5 py-px text-[11px] tabular-nums', 'bg-accent-soft text-accent' => $on, 'bg-neutral-soft text-muted' => ! $on])>{{ number_format($counts[$key]) }}</span>
            @endisset
        </a>
    @endforeach
</nav>
