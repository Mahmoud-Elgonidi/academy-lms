{{-- A lesson's materials beside the video: $items (list of Learning's MaterialLine), $icons from the page. They open in a new tab, the lesson stays. --}}
@if ($items !== [])
    <ul class="materials">
        @foreach ($items as $m)
            <li>
                <a href="{{ $m->url }}" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$m->type] ?? $icons['link'] }}"/></svg>
                    <span>{{ $m->title }}</span>
                    <em>{{ $m->teachersOnly ? __('classroom::classroom.materials.teachers_only') : __('classroom::classroom.materials.types.'.$m->type) }}</em>
                </a>
            </li>
        @endforeach
    </ul>
@endif
