{{-- Compact compliance summary for a property. Expects $c = Compliance::summary(). --}}
@php($problems = $c['expired'] + $c['missing'] + $c['expiring'])
<div class="flex flex-wrap items-center gap-1.5">
    @if ($c['expired'])<span class="lw-badge tone-bad"><x-admin.icon name="triangle-alert" class="size-3.5" />{{ $c['expired'] }} expired</span>@endif
    @if ($c['missing'])<span class="lw-badge tone-violet"><x-admin.icon name="shield-alert" class="size-3.5" />{{ $c['missing'] }} missing</span>@endif
    @if ($c['expiring'])<span class="lw-badge tone-warn"><x-admin.icon name="clock" class="size-3.5" />{{ $c['expiring'] }} expiring</span>@endif
    @if ($c['renewalBooked'])<span class="lw-badge tone-info"><x-admin.icon name="calendar-check" class="size-3.5" />{{ $c['renewalBooked'] }} booked</span>@endif
    @if (! $problems)<span class="lw-badge tone-ok"><x-admin.icon name="circle-check" class="size-3.5" />{{ $c['valid'] ? 'All in date' : 'Nothing recorded' }}</span>@endif
</div>
