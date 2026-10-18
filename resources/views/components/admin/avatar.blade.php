@props(['name', 'size' => 'md'])
@php
    $parts = preg_split('/\s+/', trim($name)) ?: [''];
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    $tones = ['tone-info', 'tone-ok', 'tone-warn', 'tone-violet', 'tone-bad', 'tone-neutral'];
    $tone = $tones[crc32($name) % count($tones)];
    $sizes = ['sm' => 'size-7 text-[11px]', 'md' => 'size-9 text-xs', 'lg' => 'size-14 text-lg'];
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full font-semibold', $tone, $sizes[$size]]) }} aria-hidden="true">{{ $initials ?: '?' }}</span>
