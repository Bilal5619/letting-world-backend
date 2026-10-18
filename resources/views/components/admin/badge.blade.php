@props(['meta'])
<span {{ $attributes->class(['lw-badge', 'tone-'.$meta['tone']]) }}>
    <x-admin.icon :name="$meta['icon']" class="size-3.5 shrink-0" />{{ $meta['label'] }}
</span>
