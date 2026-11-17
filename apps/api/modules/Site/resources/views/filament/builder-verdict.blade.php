{{-- The publish confirmation: what stops it, else what is worth a look. viewData: verdict. --}}
@php $b = 'site::builder.publish.'; @endphp
<div style="display: flex; flex-direction: column; gap: .9rem; text-align: start">
    @if ($verdict->blockers !== [])
        <div style="padding: .8rem 1rem; border-radius: .75rem; background: var(--danger-50); color: var(--danger-800); border: 1px solid var(--danger-200)">
            <strong style="display: block; margin-bottom: .4rem; font-size: .88rem">{{ __($b.'blockers') }}</strong>
            <ul style="margin: 0; padding-inline-start: 1.1rem; display: flex; flex-direction: column; gap: .3rem; font-size: .82rem; list-style: disc">
                @foreach ($verdict->blockers as $item)<li>{{ \Modules\Site\Support\PageWords::rule($item) }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if ($verdict->warnings !== [])
        <div style="padding: .8rem 1rem; border-radius: .75rem; background: var(--warning-50); color: var(--warning-800); border: 1px solid var(--warning-200)">
            <strong style="display: block; margin-bottom: .4rem; font-size: .88rem">{{ __($b.'warnings') }}</strong>
            <ul style="margin: 0; padding-inline-start: 1.1rem; display: flex; flex-direction: column; gap: .3rem; font-size: .82rem; list-style: disc">
                @foreach ($verdict->warnings as $item)<li>{{ \Modules\Site\Support\PageWords::rule($item) }}</li>@endforeach
            </ul>
        </div>
    @endif
</div>
