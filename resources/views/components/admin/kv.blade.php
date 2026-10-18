@props(['label'])
<div {{ $attributes->class(['flex items-start justify-between gap-6 border-b border-line py-2.5 text-sm last:border-0']) }}>
    <dt class="shrink-0 text-muted">{{ $label }}</dt>
    <dd class="min-w-0 text-right font-medium break-words text-ink">{{ $slot }}</dd>
</div>
