@props(['variant' => 'secondary', 'size' => null, 'href' => null, 'icon' => null, 'type' => 'button'])
@php($classes = ['lw-btn', 'lw-btn-'.$variant, 'lw-btn-sm' => $size === 'sm'])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-admin.icon :name="$icon" class="size-4 shrink-0" />@endif{{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-admin.icon :name="$icon" class="size-4 shrink-0" />@endif{{ $slot }}
    </button>
@endif
