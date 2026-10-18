@php use App\Support\AdminUi; $me = auth()->user(); @endphp
<x-admin.layout title="Settings" subtitle="Connections, prices for team-arranged jobs, the admin team and the audit log." :crumbs="[['Settings', null]]">
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            {{-- 11Done --}}
            <x-admin.card title="11Done connection" icon="plug-zap" description="Renewal bookings are handed to 11Done after payment; updates come back by webhook.">
                <x-slot:actions>
                    <span class="lw-badge {{ $elevenDone['enabled'] ? 'tone-ok' : 'tone-neutral' }}">{{ $elevenDone['enabled'] ? 'Configured' : 'Not configured' }}</span>
                    <form method="POST" action="{{ route('admin.settings.elevendone.test') }}">@csrf
                        <x-admin.button type="submit" size="sm" icon="activity">Test connection</x-admin.button>
                    </form>
                </x-slot:actions>

                @if ($test)
                    <div class="mb-4 flex items-start gap-3 rounded-xl px-4 py-3 text-sm {{ $test['ok'] ? 'bg-ok-soft text-ok' : 'bg-bad-soft text-bad' }}">
                        <x-admin.icon :name="$test['ok'] ? 'circle-check' : 'triangle-alert'" class="mt-0.5 size-4 shrink-0" />
                        <p>{{ $test['message'] }}@isset($test['ms']) <span class="opacity-75">({{ $test['ms'] }} ms)</span>@endisset</p>
                    </div>
                @endif

                <div class="grid gap-x-8 md:grid-cols-2">
                    <dl>
                        <x-admin.kv label="11Done URL"><span class="lw-mono">{{ $elevenDone['url'] ?: 'Not set' }}</span></x-admin.kv>
                        <x-admin.kv label="Partner API key"><span class="lw-mono">{{ AdminUi::mask($elevenDone['key']) }}</span></x-admin.kv>
                        <x-admin.kv label="Webhook secret">{{ $elevenDone['secret'] ? 'Set' : 'Not set' }}</x-admin.kv>
                    </dl>
                    <dl>
                        <x-admin.kv label="Bookings in 11Done">{{ $elevenDone['sent'] }}</x-admin.kv>
                        <x-admin.kv label="Waiting to send">@if ($elevenDone['failed'])<a href="{{ route('admin.bookings.index', ['tab' => 'attention']) }}" class="font-medium text-bad hover:underline">{{ $elevenDone['failed'] }} failed</a>@else 0 @endif</x-admin.kv>
                        <x-admin.kv label="Last update from 11Done">{{ $elevenDone['lastSync'] ? AdminUi::dateTime($elevenDone['lastSync']) : '—' }}</x-admin.kv>
                    </dl>
                </div>

                <div class="mt-4 rounded-xl bg-raised p-3.5 ring-1 ring-line" x-data="{ copied: false }">
                    <p class="text-xs font-medium text-muted">Webhook URL for 11Done (LETTING_WORLD_WEBHOOK_URL)</p>
                    <div class="mt-1 flex items-center gap-2">
                        <code class="lw-mono min-w-0 flex-1 truncate text-ink">{{ $elevenDone['webhook'] }}</code>
                        <button type="button" class="lw-btn lw-btn-sm lw-btn-ghost" x-on:click="navigator.clipboard.writeText(@js($elevenDone['webhook'])); copied = true; setTimeout(() => copied = false, 1500)">
                            <x-admin.icon name="copy" class="size-3.5" /><span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </button>
                    </div>
                </div>

                <h3 class="mt-6 mb-2 text-[13px] font-semibold text-ink">Services booked through 11Done</h3>
                <div class="overflow-hidden rounded-xl ring-1 ring-line">
                    <table class="lw-table">
                        <thead><tr><th>Certificate</th><th>11Done service</th></tr></thead>
                        <tbody>
                            @foreach ($elevenDone['services'] as $s)
                                <tr>
                                    <td class="font-medium text-ink">{{ $s['label'] }}</td>
                                    <td><span class="lw-mono">#{{ $s['id'] }}</span>@if (isset($test['names'][$s['id']])) <span class="text-muted">· {{ $test['names'][$s['id']] }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-muted">Run “Test connection” to see 11Done’s service names. Mapping is set in config/lettingworld.php (or ELEVENDONE_SERVICE_* in .env).</p>
            </x-admin.card>

            {{-- Team-arranged services --}}
            <x-admin.card title="Team-arranged services" icon="hard-hat" description="Services 11Done doesn’t offer. Landlords pay these prices and the team assigns a contractor." flush>
                @if ($manualServices->isEmpty())
                    <x-admin.empty icon="hard-hat" title="Everything goes through 11Done" />
                @else
                    <table class="lw-table">
                        <thead><tr><th>Service</th><th class="text-right">Price</th></tr></thead>
                        <tbody>
                            @foreach ($manualServices as $s)
                                <tr><td class="font-medium text-ink">{{ $s['label'] }}</td><td class="text-right lw-num">{{ AdminUi::money($s['price']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-admin.card>

            {{-- Audit log --}}
            <x-admin.card title="Audit log" icon="history" description="The last 25 things admins did" flush>
                @if ($log->isEmpty())
                    <x-admin.empty icon="history" title="No admin activity yet" />
                @else
                    <div class="overflow-x-auto">
                        <table class="lw-table">
                            <thead><tr><th>When</th><th>Admin</th><th>What</th><th>IP</th></tr></thead>
                            <tbody>
                                @foreach ($log as $a)
                                    <tr>
                                        <td class="whitespace-nowrap">{{ AdminUi::dateTime($a->created_at) }}</td>
                                        <td class="whitespace-nowrap">{{ $a->admin?->fullName() ?? 'Former admin' }}</td>
                                        <td class="text-ink">{{ $a->description }}</td>
                                        <td><span class="lw-mono text-xs">{{ $a->ip_address }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>

        <div class="space-y-4">
            <x-admin.card title="Other services" icon="settings">
                <dl>
                    <x-admin.kv label="Card payments (Stripe)"><span class="lw-badge {{ $stripe ? 'tone-ok' : 'tone-warn' }}">{{ $stripe ? 'Live' : 'Keys not set' }}</span></x-admin.kv>
                    <x-admin.kv label="Email sending"><span class="lw-badge {{ in_array($mail, ['log', 'array']) ? 'tone-warn' : 'tone-ok' }}">{{ in_array($mail, ['log', 'array']) ? 'Logged only ('.$mail.')' : strtoupper($mail) }}</span></x-admin.kv>
                    <x-admin.kv label="Push notifications">{{ $pushDevices }} {{ str('device')->plural($pushDevices) }} registered</x-admin.kv>
                    <x-admin.kv label="Environment">{{ app()->environment() }}</x-admin.kv>
                </dl>
            </x-admin.card>

            <x-admin.card title="Admin team" icon="users" flush>
                <x-slot:actions>
                    <x-admin.modal name="add-admin" :action="route('admin.settings.admins.store')" icon="user-plus" size="sm" trigger="Add admin" confirm="Create admin"
                        title="Add an admin" message="They can sign in to this panel straight away. Share the password with them securely.">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-admin.field name="first_name" label="First name" required />
                            <x-admin.field name="last_name" label="Last name" required />
                        </div>
                        <x-admin.field name="email" label="Email" type="email" required />
                        <x-admin.field name="password" label="Password" type="password" required hint="At least 10 characters with letters and numbers" autocomplete="new-password" />
                        <x-admin.field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
                    </x-admin.modal>
                </x-slot:actions>
                @foreach ($admins as $admin)
                    <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                        <x-admin.avatar :name="$admin->fullName()" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink">{{ $admin->fullName() }} @if ($admin->is($me))<span class="font-normal text-muted">(you)</span>@endif</p>
                            <p class="truncate text-xs text-muted">{{ $admin->email }} · {{ $admin->last_login_at ? 'seen '.$admin->last_login_at->diffForHumans() : 'never signed in' }}</p>
                        </div>
                        @if ($admin->isSuspended())<span class="lw-badge tone-bad">Suspended</span>@endif
                        @unless ($admin->is($me))
                            <form method="POST" action="{{ route('admin.settings.admins.toggle', $admin) }}">@csrf
                                <x-admin.button type="submit" size="sm" variant="ghost">{{ $admin->isSuspended() ? 'Reactivate' : 'Suspend' }}</x-admin.button>
                            </form>
                        @endunless
                    </div>
                @endforeach
            </x-admin.card>

            <x-admin.card title="Your password" icon="key-round">
                <form method="POST" action="{{ route('admin.settings.password') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-admin.field name="current_password" label="Current password" type="password" required autocomplete="current-password" />
                    <x-admin.field name="new_password" label="New password" type="password" required hint="At least 10 characters with letters and numbers" autocomplete="new-password" />
                    <x-admin.field name="new_password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
                    <div class="flex justify-end"><x-admin.button type="submit" variant="primary">Update password</x-admin.button></div>
                </form>
            </x-admin.card>
        </div>
    </div>
</x-admin.layout>
