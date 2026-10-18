<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · Letting World Admin</title>
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
<body class="min-h-screen bg-canvas font-sans text-ink">
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">

    {{-- Brand panel --}}
    <aside class="lw-sidebar relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
        {{-- Rooftop skyline, drawn from simple shapes. --}}
        <svg class="pointer-events-none absolute inset-x-0 bottom-0 h-[42%] w-full text-white" viewBox="0 0 800 300" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
            <g fill="currentColor" opacity="0.045">
                <path d="M0 300V190l60-50 60 50v110z"/><path d="M120 300V150h90v150z"/><path d="M210 300V200l45-40 45 40v100z"/>
                <path d="M300 300V120l70-60 70 60v180z"/><path d="M440 300V170h70v130z"/><path d="M510 300V210l55-45 55 45v90z"/>
                <path d="M620 300V140h80v160z"/><path d="M700 300V190l50-42 50 42v110z"/>
            </g>
            <g fill="currentColor" opacity="0.08">
                <rect x="335" y="150" width="16" height="20" rx="2"/><rect x="389" y="150" width="16" height="20" rx="2"/>
                <rect x="335" y="200" width="16" height="20" rx="2"/><rect x="389" y="200" width="16" height="20" rx="2"/>
                <rect x="140" y="180" width="14" height="18" rx="2"/><rect x="176" y="180" width="14" height="18" rx="2"/>
                <rect x="640" y="170" width="14" height="18" rx="2"/><rect x="668" y="170" width="14" height="18" rx="2"/>
            </g>
        </svg>
        <div class="pointer-events-none absolute -top-40 -right-40 size-[520px] rounded-full bg-[#2f6fde]/25 blur-3xl" aria-hidden="true"></div>

        <div class="relative flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-white text-brand shadow-lg">
                <x-admin.icon name="house" class="size-5" />
            </span>
            <div class="leading-tight">
                <p class="font-semibold text-white">Letting World</p>
                <p class="text-[11px] font-medium tracking-[0.14em] text-white/45 uppercase">Admin console</p>
            </div>
        </div>

        <div class="relative max-w-lg">
            <p class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white/80 ring-1 ring-white/15">
                <span class="size-1.5 rounded-full bg-[#4fcf93]"></span> Landlords · Tenants · Compliance · 11Done
            </p>
            <h1 class="text-[40px] leading-[1.1] font-semibold tracking-[-0.03em] text-white xl:text-[46px]">
                Every property, certificate and booking in one place.
            </h1>
            <p class="mt-5 text-[15px] leading-relaxed text-white/60">
                Keep landlords compliant, follow every renewal from payment to new certificate,
                and step in the moment something needs a person.
            </p>

            <ul class="mt-10 space-y-3">
                @foreach ([
                    ['shield-check', 'Certificate expiry tracked automatically'],
                    ['plug-zap', 'Renewals handed to 11Done the moment they’re paid'],
                    ['hard-hat', 'Specialist jobs arranged by the Letting World team'],
                ] as [$icon, $text])
                    <li class="flex items-center gap-3 text-sm text-white/75">
                        <span class="grid size-7 place-items-center rounded-lg bg-white/10 text-[#9cc0ff] ring-1 ring-white/10">
                            <x-admin.icon :name="$icon" class="size-3.5" />
                        </span>
                        {{ $text }}
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="relative text-xs text-white/35">© {{ date('Y') }} Letting World</p>
    </aside>

    {{-- Sign-in form --}}
    <main class="flex items-center justify-center px-5 py-12 sm:px-8">
        <div class="w-full max-w-[400px] lw-rise">
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <span class="grid size-10 place-items-center rounded-xl bg-brand text-white"><x-admin.icon name="house" class="size-5" /></span>
                <p class="font-semibold">Letting World <span class="font-normal text-muted">Admin</span></p>
            </div>

            <h2 class="text-[28px] font-semibold tracking-[-0.025em]">Welcome back</h2>
            <p class="mt-1.5 text-sm text-ink-2">Sign in with your admin account.</p>

            @if (session('status'))
                <div class="mt-6 flex items-center gap-2.5 rounded-xl border border-ok/20 bg-ok-soft px-3.5 py-2.5 text-sm text-ok">
                    <x-admin.icon name="circle-check" class="size-4" /> {{ session('status') }}
                </div>
            @endif
            @error('email')
                <div class="mt-6 flex items-start gap-2.5 rounded-xl border border-bad/20 bg-bad-soft px-3.5 py-2.5 text-sm text-bad" role="alert">
                    <x-admin.icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                </div>
            @enderror

            <form method="POST" action="{{ route('admin.login.attempt') }}" class="mt-7 space-y-5" x-data="{ show: false, busy: false }" x-on:submit="busy = true">
                @csrf
                <div>
                    <label for="email" class="lw-label">Email</label>
                    <div class="relative">
                        <x-admin.icon name="mail" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                               class="lw-input h-11 pl-9" placeholder="you@lettingworld.co.uk">
                    </div>
                </div>
                <div>
                    <label for="password" class="lw-label">Password</label>
                    <div class="relative">
                        <x-admin.icon name="lock" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                        <input id="password" name="password" x-bind:type="show ? 'text' : 'password'" type="password" required autocomplete="current-password"
                               class="lw-input h-11 pr-11 pl-9" placeholder="••••••••••">
                        <button type="button" x-on:click="show = !show" class="absolute top-1/2 right-2 grid size-7 -translate-y-1/2 place-items-center rounded-md text-muted hover:text-ink"
                                x-bind:aria-label="show ? 'Hide password' : 'Show password'">
                            <x-admin.icon name="eye" class="size-4" />
                        </button>
                    </div>
                </div>
                <label class="flex items-center gap-2.5 text-sm text-ink-2">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-line-strong accent-[var(--lw-brand)]">
                    Keep me signed in on this device
                </label>
                <button type="submit" class="lw-btn lw-btn-primary h-11 w-full text-[15px]" x-bind:disabled="busy">
                    <span x-show="busy" x-cloak><x-admin.icon name="loader-circle" class="size-4 animate-spin" /></span>
                    Sign in
                    <x-admin.icon name="arrow-right" class="size-4" />
                </button>
            </form>

            <p class="mt-10 flex items-center gap-2 text-xs text-muted">
                <x-admin.icon name="shield-check" class="size-3.5" />
                Restricted area. Sign-ins are logged and repeated failures are blocked.
            </p>
        </div>
    </main>
</div>
</body>
</html>
