@php
    use App\Support\AdminUi;
    use App\Models\Certificate;
    $b = $booking;
    $label = Certificate::typeLabel($b->service_type);
    $open = $b->isCancellable();
    $sync = AdminUi::syncStatus($b);
    $is11 = $b->provider === '11done';
    $date = $b->scheduled_date ?? $b->preferred_date;

    // Progress: paid → with provider → engineer booked → completed.
    $steps = [
        [in_array($b->payment_status, ['paid', 'refunded'], true) ? 'Paid' : 'Booked', 'credit-card', $b->created_at, AdminUi::paymentStatus($b->payment_status)['label']],
        [$is11 ? 'Sent to 11Done' : 'With the team', $is11 ? 'plug-zap' : 'hard-hat', $is11 ? ($b->sync_status === 'sent' ? ($b->synced_at ?? $b->created_at) : null) : $b->created_at,
            $is11 ? ($b->provider_reference ?? $sync['label'] ?? '') : 'Team arranged'],
        ['Engineer booked', 'calendar-check', in_array($b->status, ['confirmed', 'completed'], true) ? ($b->scheduled_date ?? true) : null, $b->engineer_name],
        ['Completed', 'badge-check', $b->completed_at, $b->result_certificate_id ? 'Certificate added' : ($b->status === 'completed' ? 'Certificate missing' : null)],
    ];
    $reached = collect($steps)->filter(fn ($s) => $s[2])->keys()->last() ?? -1;
@endphp
<x-admin.layout :title="$label" :crumbs="[['Bookings', route('admin.bookings.index')], [$b->reference, null]]"
    subtitle="{{ $b->reference }} · booked {{ AdminUi::dateTime($b->created_at) }} by {{ $b->landlord?->fullName() }}">
    <x-slot:actions>
        <x-admin.badge :meta="AdminUi::bookingStatus($b->status)" class="h-8 px-3 text-[13px]" />

        @if ($is11 && $open && in_array($b->sync_status, ['pending', 'failed'], true))
            <form method="POST" action="{{ route('admin.bookings.retry', $b) }}">@csrf
                <x-admin.button type="submit" icon="send">Send to 11Done now</x-admin.button>
            </form>
        @endif
        @if ($is11 && $b->sync_status === 'sent')
            <form method="POST" action="{{ route('admin.bookings.refresh', $b) }}">@csrf
                <x-admin.button type="submit" icon="refresh-cw">Refresh from 11Done</x-admin.button>
            </form>
        @endif

        @if (! $is11 && $open)
            <x-admin.modal name="schedule" :action="route('admin.bookings.schedule', $b)" icon="hard-hat" :trigger-variant="$b->status === 'requested' ? 'primary' : 'secondary'"
                :trigger="$b->status === 'requested' ? 'Assign contractor' : 'Reschedule'" :title="$b->status === 'requested' ? 'Assign a contractor' : 'Change the appointment'"
                message="The landlord{{ $b->notify_tenant ? ' and tenant' : '' }} will be told who is coming and when." confirm="Save and notify">
                <x-admin.field name="engineer_name" label="Contractor / engineer" :value="$b->engineer_name" required placeholder="e.g. Northern Gas Ltd — Tom Reid" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field name="scheduled_date" label="Date" type="date" :value="$b->scheduled_date?->toDateString() ?? $b->preferred_date?->toDateString()" required min="{{ today()->toDateString() }}" />
                    <div>
                        <label class="lw-label" for="slot">Time slot<span class="text-bad"> *</span></label>
                        <select id="slot" name="scheduled_slot" class="lw-input" required>
                            @foreach ($slots as $slot)
                                <option value="{{ $slot }}" @selected(old('scheduled_slot', $b->scheduled_slot ?? $b->time_slot) === $slot)>{{ $slot }}</option>
                            @endforeach
                        </select>
                        @error('scheduled_slot')<p class="lw-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-admin.modal>
        @endif

        @if (! $is11 && $b->status === 'confirmed')
            <x-admin.modal name="complete" :action="route('admin.bookings.complete', $b)" files wide icon="badge-check" trigger-variant="primary" trigger="Complete job"
                title="Complete the job" message="Upload the new {{ $label }}. It replaces the old one on the property and the landlord is notified." confirm="Complete and add certificate">
                @include('admin.bookings._certificate-fields')
            </x-admin.modal>
        @endif

        @if ($b->status === 'completed' && ! $b->result_certificate_id)
            <x-admin.modal name="certificate" :action="route('admin.bookings.certificate', $b)" files wide icon="upload" trigger-variant="primary" trigger="Upload certificate"
                title="Upload the certificate" message="The job is complete but the new certificate didn’t come through. Upload it here to add it to the property." confirm="Add certificate">
                @include('admin.bookings._certificate-fields')
            </x-admin.modal>
        @endif

        @if ($open)
            <x-admin.modal name="cancel" :action="route('admin.bookings.cancel', $b)" icon="ban" trigger-variant="ghost" trigger="Cancel" variant="danger"
                title="Cancel this booking?" confirm="{{ $b->payment_status === 'paid' ? 'Cancel and refund '.AdminUi::money($b->price) : 'Cancel booking' }}"
                message="{{ $is11 && $b->sync_status === 'sent' ? 'It will also be cancelled in 11Done. ' : '' }}{{ $b->payment_status === 'paid' ? 'The landlord’s card is refunded in full. ' : '' }}The landlord{{ $b->notify_tenant ? ' and tenant' : '' }} will be told.">
                <x-admin.field name="reason" label="Reason (internal)" type="textarea" placeholder="Saved to the internal notes" />
            </x-admin.modal>
        @endif
    </x-slot:actions>

    {{-- Progress --}}
    <div class="lw-card mb-4 p-5">
        @if ($b->status === 'cancelled')
            <div class="flex items-center gap-3 text-sm">
                <span class="grid size-9 place-items-center rounded-full tone-neutral"><x-admin.icon name="ban" class="size-4" /></span>
                <div>
                    <p class="font-medium text-ink">Cancelled {{ $b->cancelled_at ? AdminUi::dateTime($b->cancelled_at) : '' }}</p>
                    <p class="text-muted">{{ $b->payment_status === 'refunded' ? AdminUi::money($b->price).' refunded to the landlord.' : 'No payment to refund.' }}</p>
                </div>
            </div>
        @else
            <ol class="grid gap-4 sm:grid-cols-4">
                @foreach ($steps as $i => [$name, $icon, $when, $detail])
                    @php($done = $i <= $reached)
                    <li class="relative flex items-start gap-3 sm:flex-col sm:gap-2.5">
                        @if (! $loop->last)
                            <span @class(['absolute top-[17px] left-11 hidden h-0.5 w-[calc(100%-2.5rem)] rounded-full sm:block', 'bg-ok' => $i < $reached, 'bg-line' => $i >= $reached])></span>
                        @endif
                        <span @class(['relative z-10 grid size-9 shrink-0 place-items-center rounded-full ring-4 ring-surface',
                            'bg-ok text-white dark:text-[#04140c]' => $done, 'bg-raised text-muted border border-line' => ! $done])>
                            <x-admin.icon :name="$done ? 'check' : $icon" class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <p @class(['text-sm font-medium', 'text-ink' => $done, 'text-muted' => ! $done])>{{ $name }}</p>
                            <p class="truncate text-xs text-muted">{{ $detail ?: ($done ? '' : 'Waiting') }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.card title="Appointment" icon="calendar">
                    <dl>
                        <x-admin.kv label="Requested">{{ $b->preferred_date ? AdminUi::date($b->preferred_date).' · '.$b->time_slot : 'Tenant chooses via 11Done' }}</x-admin.kv>
                        <x-admin.kv label="Confirmed">{{ $b->scheduled_date ? AdminUi::date($b->scheduled_date).($b->scheduled_slot ? ' · '.$b->scheduled_slot : '') : '—' }}</x-admin.kv>
                        <x-admin.kv label="Engineer">{{ $b->engineer_name ?: '—' }}</x-admin.kv>
                        <x-admin.kv label="Access">{{ ['tenant' => 'Tenant lets them in', 'landlord' => 'Landlord lets them in', 'other' => 'Other arrangement'][$b->access] ?? $b->access }}</x-admin.kv>
                        <x-admin.kv label="Tenant told">{{ $b->notify_tenant ? 'Yes' : 'No' }}</x-admin.kv>
                    </dl>
                    @if ($b->access_notes)
                        <div class="mt-3 rounded-xl bg-raised px-3.5 py-3 text-sm whitespace-pre-line text-ink-2 ring-1 ring-line">{{ $b->access_notes }}</div>
                    @endif
                </x-admin.card>

                <x-admin.card title="Property" icon="building-2">
                    <a href="{{ route('admin.properties.show', $b->property) }}" class="lw-link text-[15px]">{{ $b->property->address_line1 }}</a>
                    <p class="mt-0.5 text-sm text-muted">{{ collect([$b->property->address_line2, $b->property->city, $b->property->postcode])->filter()->implode(', ') }}</p>
                    <dl class="mt-3">
                        <x-admin.kv label="Landlord"><a href="{{ route('admin.landlords.show', $b->landlord) }}" class="lw-link">{{ $b->landlord->fullName() }}</a></x-admin.kv>
                        <x-admin.kv label="Landlord phone">{{ $b->landlord->phone ?: '—' }}</x-admin.kv>
                        @forelse ($b->property->tenants as $t)
                            <x-admin.kv :label="$loop->first ? 'Tenants' : ''"><a href="{{ route('admin.tenants.show', $t) }}" class="lw-link">{{ $t->fullName() }}</a> <span class="font-normal text-muted">{{ $t->phone }}</span></x-admin.kv>
                        @empty
                            <x-admin.kv label="Tenants">None</x-admin.kv>
                        @endforelse
                    </dl>
                </x-admin.card>
            </div>

            <x-admin.card title="Timeline" icon="history" description="Everything that happened to this booking">
                @if ($timeline->isEmpty())
                    <p class="text-sm text-muted">No events recorded yet.</p>
                @else
                    <ol class="relative space-y-5 before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px before:bg-line">
                        @foreach ($timeline as $event)
                            <li class="relative pl-7">
                                <span class="absolute top-1.5 left-0 size-[15px] rounded-full border-[3px] border-surface bg-accent ring-1 ring-line"></span>
                                <p class="text-sm font-medium text-ink">{{ $event['title'] }}</p>
                                @if ($event['text'])<p class="text-sm text-ink-2">{{ $event['text'] }}</p>@endif
                                <p class="mt-0.5 text-xs text-muted">{{ AdminUi::dateTime($event['at']) }}@if ($event['who']) · {{ $event['who'] }}@endif</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-admin.card>
        </div>

        <div class="space-y-4">
            <x-admin.card title="Payment" icon="credit-card">
                <div class="flex items-end justify-between">
                    <p class="text-3xl font-semibold tracking-[-0.02em] text-ink">{{ AdminUi::money($b->price) }}</p>
                    <x-admin.badge :meta="AdminUi::paymentStatus($b->payment_status)" />
                </div>
                <dl class="mt-4">
                    <x-admin.kv label="Card">{{ $b->card_last4 ? strtoupper($b->card_brand).' •••• '.$b->card_last4 : '—' }}</x-admin.kv>
                    <x-admin.kv label="Stripe payment"><span class="lw-mono">{{ $b->payment_intent_id ?: '—' }}</span></x-admin.kv>
                </dl>
            </x-admin.card>

            @if ($is11)
                <x-admin.card title="11Done" icon="plug-zap">
                    <x-slot:actions>@if ($sync)<x-admin.badge :meta="$sync" />@endif</x-slot:actions>
                    <dl>
                        <x-admin.kv label="11Done order"><span class="lw-mono">{{ $b->provider_reference ?: '—' }}</span></x-admin.kv>
                        <x-admin.kv label="11Done status">{{ $b->provider_status ? ucfirst($b->provider_status) : '—' }}</x-admin.kv>
                        <x-admin.kv label="Last update">{{ AdminUi::dateTime($b->synced_at) }}</x-admin.kv>
                        <x-admin.kv label="Send attempts">{{ $b->sync_attempts }}</x-admin.kv>
                        <x-admin.kv label="Scheduling">{{ $b->scheduling === 'tenant' ? 'Tenant picks via 11Done link' : 'Landlord chose the time' }}</x-admin.kv>
                    </dl>
                    @if ($b->sync_error && $b->sync_status !== 'sent')
                        <div class="mt-3 flex gap-2.5 rounded-xl bg-bad-soft px-3.5 py-3 text-sm text-bad">
                            <x-admin.icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
                            <div><p class="font-medium">Last attempt failed</p><p class="mt-0.5 break-words opacity-90">{{ $b->sync_error }}</p><p class="mt-1 text-xs opacity-80">Retried automatically every 5 minutes.</p></div>
                        </div>
                    @endif
                </x-admin.card>
            @else
                <x-admin.card title="Team-arranged job" icon="hard-hat">
                    <p class="text-sm leading-relaxed text-ink-2">11Done doesn’t offer this service, so the Letting World team arranges it:
                        assign a contractor, then complete the job with the new certificate.</p>
                </x-admin.card>
            @endif

            <x-admin.card title="Certificates" icon="shield-check">
                <dl>
                    <x-admin.kv label="Renewing">
                        @if ($b->certificate)
                            Expires {{ AdminUi::date($b->certificate->expiry_date) }}
                        @else
                            —
                        @endif
                    </x-admin.kv>
                    <x-admin.kv label="New certificate">
                        @if ($b->resultCertificate)
                            <a href="{{ route('admin.files', ['certificate', $b->resultCertificate->id]) }}" target="_blank" rel="noopener" class="lw-link inline-flex items-center gap-1">
                                Valid to {{ AdminUi::date($b->resultCertificate->expiry_date) }} <x-admin.icon name="external-link" class="size-3.5" />
                            </a>
                        @else
                            {{ $b->status === 'completed' ? 'Not received' : 'After the job' }}
                        @endif
                    </x-admin.kv>
                </dl>
            </x-admin.card>

            <x-admin.card title="Internal notes" icon="sticky-note" description="Only visible to the Letting World team">
                <form method="POST" action="{{ route('admin.bookings.notes', $b) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <textarea name="admin_notes" rows="4" class="lw-input" placeholder="Calls made, contractor quotes, anything the team should know…">{{ old('admin_notes', $b->admin_notes) }}</textarea>
                    <div class="flex justify-end"><x-admin.button type="submit" size="sm">Save notes</x-admin.button></div>
                </form>
            </x-admin.card>
        </div>
    </div>
</x-admin.layout>
