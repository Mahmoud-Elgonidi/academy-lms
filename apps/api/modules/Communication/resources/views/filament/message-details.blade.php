@php $l = 'communication::communication.'; @endphp
<div style="display:flex; flex-direction:column; gap:.8rem; font-size:.875rem">
    <p style="margin:0; white-space:pre-line">{{ $n->body }}</p>
    <div style="display:flex; flex-direction:column; gap:.4rem">
        @forelse ($n->deliveries as $d)
            <div style="display:flex; flex-wrap:wrap; gap:.5rem; align-items:baseline; padding:.5rem .65rem; border:1px solid var(--gray-200); border-radius:var(--radius-lg)">
                <strong>{{ __($l.'channels.'.$d->channel) }}</strong>
                <x-filament::badge :color="['sent' => 'success', 'failed' => 'danger', 'held' => 'warning', 'queued' => 'warning'][$d->status] ?? 'gray'">{{ __($l.'statuses.'.$d->status) }}</x-filament::badge>
                @if ($d->address) <span style="color:var(--gray-600)" dir="ltr">{{ $d->address }}</span> @endif
                @if ($d->status === 'held' && $d->not_before)
                    <small style="color:var(--gray-500)">{{ __($l.'log.held_until', ['time' => $d->not_before->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(\Modules\SharedKernel\Time\Clock::pattern('D j M · H:i'))]) }}</small>
                @endif
                @if ($d->attempts > 0) <small style="color:var(--gray-500)">{{ __($l.'log.attempts', ['n' => $d->attempts]) }}</small> @endif
                @if ($d->error)
                    <div style="flex-basis:100%; color:var(--danger-700); font-size:.8rem">{{ __($l.'reasons.'.$d->error) !== $l.'reasons.'.$d->error ? __($l.'reasons.'.$d->error) : $d->error }}</div>
                @endif
            </div>
        @empty
            <span style="color:var(--gray-500)">{{ __($l.'log.inbox_only') }}</span>
        @endforelse
    </div>
</div>
