{{--
    On every panel page while the platform team is signed in as this person:
    who is signed in, as whom, the time left (the page reloads when it runs
    out, and the session ends), and leaving.
--}}
@php $imp = $lmsImpersonation ?? null; @endphp
@if ($imp)
    @php $l = 'identity::impersonation.banner.'; @endphp
    <style>
        .lms-imp { position: relative; z-index: 40; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .35rem .9rem; padding: .45rem 1rem; background: var(--warning-500, #f59e0b); color: #1c1917; font-size: .85rem; font-weight: 600; }
        .lms-imp svg { width: 1.05rem; height: 1.05rem; }
        .lms-imp > span { display: inline-flex; align-items: center; gap: .4rem; }
        .lms-imp time { font-variant-numeric: tabular-nums; padding: .05rem .5rem; border-radius: 999px; background: rgb(255 255 255 / .45); }
        .lms-imp button { font: inherit; font-weight: 700; padding: .25rem .8rem; border-radius: 999px; border: 0; background: #1c1917; color: white; cursor: pointer; }
        .lms-imp button:hover { background: #292524; }
    </style>
    <div class="lms-imp" role="status"
         x-data="{ left: Math.max(0, Math.round(({{ $imp->expires_at->getTimestamp() }} * 1000 - Date.now()) / 1000)), timer: null,
                   get clock() { const m = Math.floor(this.left / 60), s = this.left % 60; return m + ':' + String(s).padStart(2, '0') } }"
         x-init="timer = setInterval(() => { left = Math.max(0, left - 1); if (left === 0) { clearInterval(timer); window.location.reload() } }, 1000)">
        <span>
            {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedLifebuoy) }}
            {{ __($l.'text', ['admin' => $imp->admin_name, 'user' => auth()->user()?->name]) }}
        </span>
        <span>{{ __($l.'left') }} <time x-text="clock">{{ $imp->minutes }}:00</time></span>
        <form method="post" action="{{ route('identity.impersonate.exit') }}">
            @csrf
            <button type="submit">{{ __($l.'exit') }}</button>
        </form>
    </div>
@endif
