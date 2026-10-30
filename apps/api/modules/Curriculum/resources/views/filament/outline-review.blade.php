@php
    $rules = \Modules\Curriculum\Domain\Outline\OutlineRules::class;
    $r = 'curriculum::outline.review.';
    $over = $counts['levels'] > $rules::LEVELS_MAX || $counts['lessons'] > $rules::LESSONS_MAX;
@endphp

<div class="cb-outline">
    <style>
        .cb-outline { display: flex; flex-direction: column; gap: .75rem; }
        .cb-outline-sum { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .85rem; font-weight: 600; color: var(--gray-700); }
        .cb-outline-sum[data-over] { color: var(--danger-700); }
        .cb-outline-sum small { font-weight: 400; color: var(--gray-500); }
        .cb-outline ol { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .2rem; max-height: 60vh; overflow-y: auto; }
        .cb-orow { display: flex; align-items: center; gap: .5rem; padding: .3rem .5rem; border-radius: var(--radius-md, .5rem); }
        .cb-orow[data-kind="level"] { background: var(--gray-50); margin-top: .35rem; }
        .cb-orow[data-depth="1"] { margin-inline-start: 1.5rem; }
        .cb-orow[data-depth="2"] { margin-inline-start: 3rem; }
        .cb-orow[data-off] { opacity: .45; }
        .cb-orow input[type="text"] { flex: 1; min-width: 0; border: 1px solid transparent; background: transparent; border-radius: .4rem; padding: .2rem .4rem; font-size: .875rem; color: inherit; }
        .cb-orow input[type="text"]:focus { border-color: var(--primary-500); background: white; outline: none; }
        .cb-orow[data-kind="level"] input[type="text"] { font-weight: 700; }
        .cb-orow-kind { flex: none; font-size: .68rem; font-weight: 700; padding: .05rem .45rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); white-space: nowrap; }
        .cb-orow[data-kind="unit"] .cb-orow-kind { background: var(--gray-100); color: var(--gray-700); }
        .cb-orow[data-kind="lesson"] .cb-orow-kind { background: transparent; color: var(--gray-500); }
        .cb-orow-note { flex: none; max-width: 40%; font-size: .72rem; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cb-orow-quran { flex: none; font-size: .68rem; font-weight: 600; padding: 0 .45rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); }
        .cb-orow button { flex: none; border: 0; background: none; color: var(--gray-400); cursor: pointer; padding: .15rem .35rem; border-radius: .35rem; font-size: .8rem; }
        .cb-orow button:hover { color: var(--danger-600); background: var(--danger-50); }
        .cb-outline-empty { color: var(--gray-500); font-size: .875rem; }
        .dark .cb-orow[data-kind="level"] { background: var(--gray-800); }
        .dark .cb-orow input[type="text"]:focus { background: var(--gray-900); }
        .dark .cb-outline-sum { color: var(--gray-300); }
        .dark .cb-orow-kind, .dark .cb-orow-quran { background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-300); }
        .dark .cb-orow[data-kind="unit"] .cb-orow-kind { background: var(--gray-800); color: var(--gray-300); }
    </style>

    <div class="cb-outline-sum" @if ($over) data-over @endif>
        <span>{{ __($r.'counts', $counts) }}</span>
        <small>{{ __($r.'limits', ['levels' => $rules::LEVELS_MAX, 'lessons' => $rules::LESSONS_MAX]) }}</small>
    </div>

    @if ($rows === [])
        <p class="cb-outline-empty">{{ __($r.'empty') }}</p>
    @else
        <ol>
            @foreach ($rows as $i => $row)
                <li class="cb-orow" wire:key="outline-{{ $row['key'] ?? $i }}" data-kind="{{ $row['kind'] }}" data-depth="{{ $row['depth'] }}" @unless ($counting[$i] ?? true) data-off @endunless>
                    <input type="checkbox" wire:model.live="outline.{{ $i }}.on" aria-label="{{ __($r.'include') }}" class="fi-checkbox-input">
                    <span class="cb-orow-kind">{{ __('curriculum::curriculum.kinds.'.$row['kind']) }}</span>
                    <input type="text" wire:model.blur="outline.{{ $i }}.{{ $field }}" maxlength="{{ $rules::TITLE_MAX }}" dir="auto" aria-label="{{ __('curriculum::curriculum.builder.quick.title') }}">
                    @if (! empty($row['quran']))
                        <span class="cb-orow-quran">{{ __($r.'quran') }}</span>
                    @endif
                    @if (! empty($row['objective']))
                        <span class="cb-orow-note" title="{{ $row['objective'] }}">{{ __($r.'objective') }}: {{ $row['objective'] }}</span>
                    @endif
                    <button type="button" wire:click="dropOutlineRow({{ $i }})" title="{{ __($r.'remove') }}" aria-label="{{ __($r.'remove') }}">✕</button>
                </li>
            @endforeach
        </ol>
    @endif
</div>
