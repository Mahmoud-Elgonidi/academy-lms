{{-- The materials of a few roadmap lessons: $lessons (each with its 'materials'). Files open through their signed address, in a new tab. --}}
@if ($lessons !== [])
    @php
        $paths = [
            'pdf' => 'M14 3H7.5A1.5 1.5 0 0 0 6 4.5v15A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V7l-4-4ZM14 3v4h4M9 13h6M9 17h4',
            'audio' => 'M9 18V6l10-2v12M9 18a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm10-2a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z',
            'video' => 'M4.5 6h9A1.5 1.5 0 0 1 15 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 3 16.5v-9A1.5 1.5 0 0 1 4.5 6ZM15 10.5l6-3.5v10l-6-3.5',
            'image' => 'M4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5ZM3 16l5-5 4 4 3-3 6 6',
            'link' => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
        ];
    @endphp
    <div class="rm-mats">
        @foreach ($lessons as $l)
            <div class="rm-mat">
                <span>{{ $l['title'] }}</span>
                @foreach ($l['materials'] as $m)
                    <a href="{{ $m['url'] }}" target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$m['type']] ?? $paths['link'] }}"/></svg>
                        {{ $m['title'] }}
                        <small>{{ $m['teachers_only'] ? __('learning::progress.teachers_only') : __('learning::progress.material_types.'.$m['type']) }}</small>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
