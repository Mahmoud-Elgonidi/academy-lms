{{-- A block's picture or video (Filament\Forms\MediaPicker): the chosen file, and choose / upload / remove. --}}
@php
    $chosen = $getChosen();
    $kind = $getKind();
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="lms-mp" @if ($chosen) data-chosen @endif>
        <div class="lms-mp__thumb">
            @if ($chosen && $chosen['kind'] === 'image')
                <img src="{{ $chosen['url'] }}" alt="" loading="lazy" decoding="async">
            @elseif ($chosen)
                <video src="{{ $chosen['url'] }}#t=0.5" preload="metadata" muted playsinline></video>
            @else
                <x-filament::icon :icon="$kind === 'video' ? 'heroicon-o-film' : 'heroicon-o-photo'" class="h-7 w-7" />
            @endif
        </div>
        <div class="lms-mp__body">
            <span class="lms-mp__name">{{ $chosen['name'] ?? __('site::builder.media.none') }}</span>
            @if ($chosen)
                <span class="lms-mp__meta">{{ $chosen['meta'] }}</span>
            @endif
            <div class="lms-mp__actions">
                {{ $getAction('choose') }}
                {{ $getAction('upload') }}
                {{ $getAction('clear') }}
            </div>
        </div>
    </div>
</x-dynamic-component>
