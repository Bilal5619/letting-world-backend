@props(['title' => null, 'description' => null, 'flush' => false, 'icon' => null])
<section {{ $attributes->class(['lw-card overflow-hidden']) }}>
    @if ($title || isset($actions))
        <header class="lw-card-head">
            <div class="flex min-w-0 items-center gap-3">
                @if ($icon)
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-accent-soft text-accent">
                        <x-admin.icon :name="$icon" class="size-4" />
                    </span>
                @endif
                <div class="min-w-0">
                    <h2 class="lw-card-title">{{ $title }}</h2>
                    @if ($description)<p class="mt-0.5 text-[13px] text-muted">{{ $description }}</p>@endif
                </div>
            </div>
            @isset($actions)<div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['p-5' => ! $flush])>{{ $slot }}</div>
</section>
