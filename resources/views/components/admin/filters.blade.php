{{-- Search box + optional selects (they submit on change). Keeps the listed query params. --}}
@props(['placeholder' => 'Search…', 'keep' => []])
<form method="GET" {{ $attributes->class(['flex flex-wrap items-center gap-2 border-b border-line px-5 py-3']) }}>
    @foreach ($keep as $param)
        @if (filled(request($param)))<input type="hidden" name="{{ $param }}" value="{{ request($param) }}">@endif
    @endforeach
    <div class="relative min-w-[220px] flex-1">
        <x-admin.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" class="lw-input h-9 pl-9" aria-label="{{ $placeholder }}">
    </div>
    {{ $slot }}
    <x-admin.button type="submit" size="sm" class="h-9">Search</x-admin.button>
    @if (filled(request('q')))
        <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}" class="lw-btn lw-btn-sm lw-btn-ghost h-9">Clear</a>
    @endif
</form>
