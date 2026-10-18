{{--
    Button that opens a dialog with a form. Re-opens itself after a failed submit
    (validation errors) so the admin sees what to fix.
--}}
@props([
    'name', 'action', 'title', 'message' => null, 'confirm' => 'Confirm', 'variant' => 'primary', 'method' => 'POST',
    'trigger', 'triggerVariant' => 'secondary', 'icon' => null, 'size' => null, 'files' => false, 'wide' => false,
])
<div x-data="{ open: @js(old('_modal') === $name), busy: false }" {{ $attributes->class('contents') }}>
    <x-admin.button :variant="$triggerVariant" :icon="$icon" :size="$size" x-on:click="open = true">{{ $trigger }}</x-admin.button>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-label="{{ $title }}" x-on:keydown.escape.window="open = false">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-[#050b18]/55 backdrop-blur-[2px]" x-on:click="open = false"></div>
            <form x-show="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                  x-trap.noscroll.inert="open" x-on:submit="busy = true" method="POST" action="{{ $action }}" @if ($files) enctype="multipart/form-data" @endif
                  @class(['lw-card relative max-h-[90vh] w-full overflow-y-auto p-6', $wide ? 'max-w-xl' : 'max-w-md']) style="box-shadow: var(--lw-shadow-lg)">
                @csrf
                @if (strtoupper($method) !== 'POST') @method($method) @endif
                <input type="hidden" name="_modal" value="{{ $name }}">
                <div class="flex items-start justify-between gap-4">
                    <h3 class="text-base font-semibold tracking-[-0.01em] text-ink">{{ $title }}</h3>
                    <button type="button" class="-m-1 rounded-md p-1 text-muted hover:bg-neutral-soft hover:text-ink" x-on:click="open = false" aria-label="Close">
                        <x-admin.icon name="x" class="size-4" />
                    </button>
                </div>
                @if ($message)<p class="mt-2 text-sm leading-relaxed text-ink-2">{{ $message }}</p>@endif
                @if (! $slot->isEmpty())<div class="mt-5 space-y-4">{{ $slot }}</div>@endif
                <div class="mt-6 flex justify-end gap-2">
                    <x-admin.button variant="ghost" x-on:click="open = false">Cancel</x-admin.button>
                    <x-admin.button :variant="$variant" type="submit" x-bind:disabled="busy">
                        <span x-show="busy" x-cloak><x-admin.icon name="loader-circle" class="size-4 animate-spin" /></span>{{ $confirm }}
                    </x-admin.button>
                </div>
            </form>
        </div>
    </template>
</div>
