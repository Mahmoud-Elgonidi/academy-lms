{{-- One recording's check, word by word, for the teacher alone (RecitationPresenter::check). $check --}}
@if ($check)
    @php
        $r = 'learning::recitation.';
        $extra = collect($check['extra'])->groupBy('after');
        $n = 0;
    @endphp
    @once
        <style>
            .lms-recite { display: flex; flex-direction: column; gap: .4rem; padding-top: .5rem; border-top: 1px dashed var(--gray-200); }
            .lms-recite-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
            .lms-recite-text { font-size: 1.15rem; line-height: 2.3; }
            .lms-recite-text span { border-radius: .3rem; padding: 0 .12rem; }
            .lms-recite .w-missing { color: var(--warning-800); background: var(--warning-50); text-decoration: line-through dotted; }
            .lms-recite .w-wrong { color: var(--danger-700); background: var(--danger-50); }
            .lms-recite .w-wrong small { margin-inline-start: .2rem; font-size: .7em; color: var(--gray-600); }
            .lms-recite .w-extra { color: var(--info-700); background: var(--info-50); font-style: italic; }
            .lms-recite .w-verse { color: var(--gray-400); font-size: .75em; }
            .lms-recite-legend { display: flex; flex-wrap: wrap; gap: .35rem .75rem; font-size: .75rem; }
            .lms-recite-legend span { border-radius: .3rem; padding: 0 .3rem; }
        </style>
    @endonce
    <div class="lms-recite">
        <div class="lms-recite-head">
            <strong>{{ __($r.'title') }}</strong>
            <x-filament::badge :color="match ($check['status']) { 'ready' => 'success', 'failed' => 'danger', default => 'gray' }">{{ $check['status_label'] }}</x-filament::badge>
        </div>
        @if ($check['status'] === 'failed')
            <p style="margin:0; color:var(--gray-600)">{{ $check['failure'] }}</p>
        @elseif ($check['status'] === 'ready')
            <p style="margin:0; font-weight:600">{{ $check['summary_label'] }}</p>
            <div class="lms-recite-text" dir="rtl" lang="ar">
                @foreach ($extra->get(0, []) as $e)<span class="w-extra" title="{{ __($r.'states.extra') }}">{{ $e['text'] }}</span> @endforeach
                @foreach ($check['verses'] as $v)
                    @foreach ($v['words'] as $w)
                        @php $n++; @endphp
                        @if ($w['state'] === 'wrong')
                            <span class="w-wrong" title="{{ __($r.'instead', ['word' => $w['heard']]) }}">{{ $w['text'] }}<small dir="rtl">({{ $w['heard'] }})</small></span>
                        @elseif ($w['state'] === 'missing')
                            <span class="w-missing" title="{{ __($r.'states.missing') }}">{{ $w['text'] }}</span>
                        @else
                            <span>{{ $w['text'] }}</span>
                        @endif
                        @foreach ($extra->get($n, []) as $e)<span class="w-extra" title="{{ __($r.'states.extra') }}">{{ $e['text'] }}</span> @endforeach
                    @endforeach
                    @if ($v['verse'])<span class="w-verse" title="{{ __($r.'verse', ['number' => $v['verse']]) }}">({{ $v['verse'] }})</span>@endif
                @endforeach
            </div>
            <div class="lms-recite-legend">
                <span class="w-missing">{{ __($r.'states.missing') }}</span>
                <span class="w-wrong">{{ __($r.'states.wrong') }}</span>
                <span class="w-extra">{{ __($r.'states.extra') }}</span>
            </div>
            @if (filled($check['heard']))
                <details>
                    <summary style="cursor:pointer; color:var(--gray-600)">{{ __($r.'heard') }}</summary>
                    <p dir="rtl" lang="ar" style="margin:.3rem 0 0">{{ $check['heard'] }}</p>
                </details>
            @endif
        @endif
        <small style="color:var(--gray-500)">{{ $check['note'] }}</small>
    </div>
@endif
