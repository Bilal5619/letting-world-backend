@props(['icon' => 'inbox', 'title', 'message' => null])
<div {{ $attributes->class(['flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <div class="mb-4 grid size-12 place-items-center rounded-2xl bg-neutral-soft text-muted">
        <x-admin.icon :name="$icon" class="size-6" />
    </div>
    <p class="font-semibold text-ink">{{ $title }}</p>
    @if ($message)<p class="mt-1 max-w-sm text-sm text-muted">{{ $message }}</p>@endif
    {{ $slot }}
</div>
