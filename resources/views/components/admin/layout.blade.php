{{-- Admin shell: navy sidebar, top bar with ⌘K search, page header, toasts. --}}
@props(['title', 'subtitle' => null, 'crumbs' => []])
@php
    $admin = auth()->user();
    $nav = [
        'Overview' => [
            ['Dashboard', 'admin.dashboard', 'layout-dashboard', null],
        ],
        'Operations' => [
            ['Bookings', 'admin.bookings.index', 'calendar-check', $navCounts['bookings'] ?? 0],
            ['Certificates', 'admin.certificates.index', 'shield-check', $navCounts['certificates'] ?? 0],
            ['Maintenance', 'admin.maintenance.index', 'wrench', $navCounts['maintenance'] ?? 0],
            ['Payments', 'admin.payments.index', 'credit-card', null],
        ],
        'People' => [
            ['Landlords', 'admin.landlords.index', 'user-round', null],
            ['Tenants', 'admin.tenants.index', 'users', null],
        ],
        'Portfolio' => [
            ['Properties', 'admin.properties.index', 'building-2', null],
        ],
        'System' => [
            ['Settings', 'admin.settings.index', 'settings', null],
        ],
    ];
    $section = fn (string $route) => str($route)->beforeLast('.')->append('.*')->toString();
    $shortcuts = collect($nav)->flatten(1)->map(fn ($item) => [
        'group' => 'Go to', 'title' => $item[0], 'subtitle' => null, 'icon' => $item[2], 'url' => route($item[1]),
    ])->values();
    $elevenDoneOn = app(\App\Support\ElevenDone::class)->enabled();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Letting World Admin</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230B2A5B'/%3E%3Cpath d='M9 15.5 16 9l7 6.5V23a1 1 0 0 1-1 1h-4v-5h-4v5h-4a1 1 0 0 1-1-1z' fill='white'/%3E%3C/svg%3E">
    <script>
        (function () {
            var m = 'system';
            try { m = localStorage.getItem('lw-admin-theme') || 'system'; } catch (e) {}
            if (m === 'dark' || (m === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        })();
    </script>
    @fonts
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="min-h-screen bg-canvas font-sans text-ink" x-data="{ nav: false }">
    <a href="#main" class="sr-only z-50 rounded-lg bg-surface px-3 py-2 focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Skip to content</a>

    {{-- Sidebar --}}
    <div x-show="nav" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-[#050b18]/60 lg:hidden" x-on:click="nav = false"></div>
    <aside class="lw-sidebar fixed inset-y-0 left-0 z-50 flex w-[264px] -translate-x-full flex-col transition-transform duration-200 lg:translate-x-0"
           x-bind:class="nav && '!translate-x-0'" aria-label="Main navigation">
        <div class="flex h-16 items-center gap-3 px-5">
            <span class="grid size-9 place-items-center rounded-xl bg-white text-brand shadow-[0_6px_16px_-6px_rgba(0,0,0,0.5)]">
                <x-admin.icon name="house" class="size-[18px]" />
            </span>
            <div class="leading-tight">
                <p class="text-[15px] font-semibold tracking-[-0.01em] text-white">Letting World</p>
                <p class="text-[11px] font-medium tracking-[0.12em] text-white/45 uppercase">Admin console</p>
            </div>
        </div>

        <button type="button" x-on:click="$dispatch('open-palette')"
                class="mx-4 mt-1 mb-2 flex items-center gap-2.5 rounded-lg bg-white/[0.07] px-3 py-2 text-left text-[13px] text-white/55 ring-1 ring-white/10 transition hover:bg-white/[0.1] hover:text-white/80">
            <x-admin.icon name="search" class="size-4" />
            <span class="flex-1">Search…</span>
            <span class="lw-kbd">⌘</span><span class="lw-kbd -ml-1.5">K</span>
        </button>

        <nav class="flex-1 space-y-5 overflow-y-auto px-4 py-3">
            @foreach ($nav as $group => $items)
                <div>
                    <p class="mb-1.5 px-3 text-[10.5px] font-semibold tracking-[0.14em] text-white/35 uppercase">{{ $group }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($items as [$label, $route, $icon, $count])
                            @php($active = request()->routeIs($route) || ($route !== 'admin.dashboard' && request()->routeIs($section($route))))
                            <li>
                                <a href="{{ route($route) }}" class="lw-nav-item" @if ($active) aria-current="page" @endif>
                                    <x-admin.icon :name="$icon" class="size-[18px] shrink-0" />
                                    <span class="flex-1">{{ $label }}</span>
                                    @if ($count)
                                        <span class="rounded-full bg-[#f2b84b] px-1.5 py-px text-[10.5px] font-semibold text-[#2b1a00] tabular-nums" title="Needs attention">{{ $count }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="m-4 rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/10">
            <div class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-full bg-[#7aa5f7]/20 text-xs font-semibold text-[#cfe0ff]">{{ $admin->initials() }}</span>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-[13px] font-medium text-white">{{ $admin->fullName() }}</p>
                    <p class="truncate text-[11.5px] text-white/45">{{ $admin->email }}</p>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="grid size-8 place-items-center rounded-lg text-white/50 transition hover:bg-white/10 hover:text-white" title="Sign out" aria-label="Sign out">
                        <x-admin.icon name="log-out" class="size-4" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-[264px]">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 border-b border-line bg-canvas/80 backdrop-blur-xl">
            <div class="flex h-16 w-full items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button type="button" class="lw-btn lw-btn-ghost -ml-2 px-2 lg:hidden" x-on:click="nav = true" aria-label="Open menu">
                    <x-admin.icon name="menu" class="size-5" />
                </button>
                <nav aria-label="Breadcrumb" class="hidden min-w-0 items-center gap-1.5 text-[13px] text-muted sm:flex">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-ink">Admin</a>
                    @foreach ($crumbs as [$label, $url])
                        <x-admin.icon name="chevron-right" class="size-3.5 text-line-strong" />
                        @if ($url)
                            <a href="{{ $url }}" class="truncate hover:text-ink">{{ $label }}</a>
                        @else
                            <span class="truncate font-medium text-ink">{{ $label }}</span>
                        @endif
                    @endforeach
                </nav>
                <div class="flex-1"></div>

                <span @class(['hidden items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-medium md:inline-flex',
                    'border-ok/25 bg-ok-soft text-ok' => $elevenDoneOn, 'border-line bg-surface text-muted' => ! $elevenDoneOn])
                    title="{{ $elevenDoneOn ? '11Done connection is configured' : '11Done is not configured' }}">
                    <span class="relative flex size-2">
                        @if ($elevenDoneOn)<span class="absolute inline-flex size-full animate-ping rounded-full bg-ok opacity-40"></span>@endif
                        <span @class(['relative inline-flex size-2 rounded-full', 'bg-ok' => $elevenDoneOn, 'bg-muted' => ! $elevenDoneOn])></span>
                    </span>
                    11Done {{ $elevenDoneOn ? 'connected' : 'off' }}
                </span>

                <button type="button" class="lw-btn lw-btn-ghost px-2" x-on:click="$dispatch('open-palette')" aria-label="Search">
                    <x-admin.icon name="search" class="size-[18px]" />
                </button>
                <button type="button" class="lw-btn lw-btn-ghost px-2" x-on:click="$store.theme.cycle()"
                        x-bind:title="'Theme: ' + $store.theme.mode" aria-label="Switch theme">
                    <span x-show="$store.theme.mode === 'light'"><x-admin.icon name="sun" class="size-[18px]" /></span>
                    <span x-show="$store.theme.mode === 'dark'" x-cloak><x-admin.icon name="moon" class="size-[18px]" /></span>
                    <span x-show="$store.theme.mode === 'system'" x-cloak><x-admin.icon name="monitor" class="size-[18px]" /></span>
                </button>
            </div>
        </header>

        <main id="main" class="w-full px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between lw-rise">
                <div class="min-w-0">
                    <h1 class="text-[26px] leading-tight font-semibold tracking-[-0.025em] text-ink">{{ $title }}</h1>
                    @if ($subtitle)<p class="mt-1 text-sm text-ink-2">{{ $subtitle }}</p>@endif
                </div>
                @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
            </div>

            @if ($errors->any() && ! old('_modal'))
                <div class="mb-5 flex items-start gap-3 rounded-xl border border-bad/25 bg-bad-soft px-4 py-3 text-sm text-bad" role="alert">
                    <x-admin.icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            <div class="lw-rise" style="animation-delay: 60ms">
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- ⌘K palette --}}
    <div x-data="palette(@js(route('admin.search')), @js($shortcuts))" x-cloak>
        <div x-show="open" class="fixed inset-0 z-[60] flex items-start justify-center px-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="Search">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-[#050b18]/55 backdrop-blur-sm" x-on:click="close()"></div>
            <div x-show="open" x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-[0.98]" x-transition:enter-end="opacity-100 scale-100"
                 x-trap.noscroll="open" class="lw-card relative w-full max-w-xl overflow-hidden" style="box-shadow: var(--lw-shadow-lg)"
                 x-on:keydown.escape="close()" x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.enter.prevent="go(items[active])">
                <div class="flex items-center gap-3 border-b border-line px-4">
                    <x-admin.icon name="search" class="size-5 text-muted" />
                    <input x-ref="input" x-model="query" x-on:input="search()" type="text" placeholder="Search landlords, tenants, properties, bookings…"
                           class="h-14 flex-1 bg-transparent text-[15px] text-ink placeholder:text-muted focus:outline-none" aria-label="Search">
                    <span x-show="loading"><x-admin.icon name="loader-circle" class="size-4 animate-spin text-muted" /></span>
                    <kbd class="rounded border border-line bg-raised px-1.5 py-0.5 font-mono text-[10px] text-muted">ESC</kbd>
                </div>
                <ul class="max-h-[50vh] overflow-y-auto p-2" role="listbox">
                    <template x-for="(item, i) in items" :key="item.url + i">
                        <li>
                            <template x-if="i === 0 || items[i - 1].group !== item.group">
                                <p class="px-3 pt-3 pb-1.5 text-[10.5px] font-semibold tracking-[0.12em] text-muted uppercase" x-text="item.group"></p>
                            </template>
                            <a :id="'palette-item-' + i" :href="item.url" x-on:mouseenter="active = i" role="option" :aria-selected="active === i"
                               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm" :class="active === i ? 'bg-accent-soft text-ink' : 'text-ink-2'">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-raised ring-1 ring-line" :class="active === i && 'text-accent'" x-html="$refs['icon-' + item.icon]?.innerHTML ?? $refs['icon-circle-dot'].innerHTML"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-ink" x-text="item.title"></span>
                                    <span class="block truncate text-xs text-muted" x-show="item.subtitle" x-text="item.subtitle"></span>
                                </span>
                                <span x-show="active === i"><x-admin.icon name="arrow-right" class="size-4 text-accent" /></span>
                            </a>
                        </li>
                    </template>
                    <li x-show="query.trim() && !loading && !results.length" class="px-3 py-10 text-center text-sm text-muted">
                        No matches for “<span x-text="query"></span>”
                    </li>
                </ul>
                <div class="flex items-center gap-4 border-t border-line bg-raised px-4 py-2.5 text-[11.5px] text-muted">
                    <span><kbd class="font-mono">↑↓</kbd> to move</span><span><kbd class="font-mono">↵</kbd> to open</span><span class="ml-auto">Tip: press <kbd class="font-mono">/</kbd> anywhere</span>
                </div>
                {{-- Icon templates used by results. --}}
                <div class="hidden">
                    @foreach (['layout-dashboard', 'calendar-check', 'shield-check', 'wrench', 'credit-card', 'user-round', 'users', 'building-2', 'settings', 'circle-dot', 'house'] as $i)
                        <span x-ref="icon-{{ $i }}"><x-admin.icon :name="$i" class="size-4" /></span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Toasts --}}
    <div class="pointer-events-none fixed right-4 bottom-4 z-[70] flex w-full max-w-sm flex-col gap-2" aria-live="polite"
         x-data x-init="@if (session('status')) $store.toasts.push(@js(session('status')), @js(session('tone', 'ok'))) @endif">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div x-transition class="lw-card pointer-events-auto flex items-start gap-3 p-3.5 pr-3" style="box-shadow: var(--lw-shadow-lg)">
                <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full" :class="'tone-' + toast.tone">
                    <span x-show="toast.tone === 'ok'"><x-admin.icon name="check" class="size-3.5" /></span>
                    <span x-show="toast.tone !== 'ok'"><x-admin.icon name="info" class="size-3.5" /></span>
                </span>
                <p class="flex-1 text-sm text-ink" x-text="toast.message"></p>
                <button type="button" class="rounded p-0.5 text-muted hover:text-ink" x-on:click="$store.toasts.dismiss(toast.id)" aria-label="Dismiss">
                    <x-admin.icon name="x" class="size-4" />
                </button>
            </div>
        </template>
    </div>
</body>
</html>
