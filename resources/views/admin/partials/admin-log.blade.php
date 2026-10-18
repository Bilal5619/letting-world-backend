{{-- Admin actions taken on this record. Expects $actions. --}}
<x-admin.card title="Admin activity" icon="history" description="Actions taken by the Letting World team">
    @if ($actions->isEmpty())
        <p class="text-sm text-muted">No admin actions yet.</p>
    @else
        <ol class="space-y-3">
            @foreach ($actions as $a)
                <li class="flex items-start gap-3 text-sm">
                    <x-admin.avatar :name="$a->admin?->fullName() ?? 'Admin'" size="sm" />
                    <div class="min-w-0">
                        <p class="text-ink">{{ $a->description }}</p>
                        <p class="text-xs text-muted">{{ $a->admin?->fullName() ?? 'Former admin' }} · {{ \App\Support\AdminUi::dateTime($a->created_at) }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-admin.card>
