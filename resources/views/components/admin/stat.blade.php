{{-- Stat tile: label · value · optional delta (vs a named period) · optional hint. --}}
@props(['label', 'value', 'icon' => null, 'hint' => null, 'delta' => null, 'deltaTone' => 'neutral', 'href' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['lw-card group relative block p-5 transition', 'hover:border-line-strong' => $href]) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-[13px] font-medium text-ink-2">{{ $label }}</p>
        @if ($icon)
            <span class="grid size-8 place-items-center rounded-lg bg-raised text-muted ring-1 ring-line transition group-hover:text-accent">
                <x-admin.icon :name="$icon" class="size-4" />
            </span>
        @endif
    </div>
    <p class="mt-3 text-[28px] leading-none font-semibold tracking-[-0.02em] text-ink">{{ $value }}</p>
    <div class="mt-2.5 flex flex-wrap items-center gap-2 text-xs text-muted">
        @if ($delta)
            <span class="lw-badge tone-{{ $deltaTone }} px-1.5">{{ $delta }}</span>
        @endif
        @if ($hint)<span>{{ $hint }}</span>@endif
    </div>
</{{ $tag }}>
