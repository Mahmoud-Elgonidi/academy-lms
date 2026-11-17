{{-- The picker's window: the library's files of one kind as radios. viewData: files, kind. --}}
@php $statePath = $getStatePath(); @endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($files === [])
        <div class="lms-empty">
            <x-filament::icon :icon="$kind === 'video' ? 'heroicon-o-film' : 'heroicon-o-photo'" />
            <span>{{ __('site::builder.media.empty_kind') }}</span>
        </div>
    @else
        <div class="lms-lib" role="radiogroup">
            @foreach ($files as $file)
                @php $id = 'pick-'.str_replace('.', '-', $statePath).'-'.$file['id']; @endphp
                <label class="lms-lib__item" for="{{ $id }}" wire:key="{{ $id }}" style="cursor: pointer">
                    <input type="radio" id="{{ $id }}" name="{{ $statePath }}" value="{{ $file['id'] }}" wire:model="{{ $statePath }}">
                    <div class="lms-lib__img">
                        @if ($file['kind'] === 'image')
                            <img src="{{ $file['url'] }}" alt="" loading="lazy" decoding="async">
                        @else
                            <video src="{{ $file['url'] }}#t=0.5" preload="metadata" muted playsinline></video>
                        @endif
                    </div>
                    <div class="lms-lib__body">
                        <span class="lms-lib__name">{{ $file['name'] }}</span>
                        <span class="lms-lib__meta">{{ $file['meta'] }}</span>
                    </div>
                </label>
            @endforeach
        </div>
    @endif
</x-dynamic-component>
