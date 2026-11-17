{{-- The Media tab: the academy's library (upload, alt text, delete; "in use" says where). --}}
@php $b = 'site::builder.'; @endphp
<div>
    <div class="lms-lib-head">
        <div class="lms-lib-filter" role="group">
            @foreach (['all' => __($b.'media.filter_all'), 'image' => __($b.'media.kinds.image'), 'video' => __($b.'media.kinds.video')] as $kind => $label)
                <button type="button" wire:click="$set('mediaKind', '{{ $kind }}')" aria-pressed="{{ $mediaKind === $kind ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        <span class="lms-lib-totals">{{ __($b.'media.totals', ['count' => $library['count'], 'used' => $library['used'], 'limit' => $library['limit']]) }}</span>
        {{ $this->uploadMediaAction }}
    </div>

    @if ($library['files'] === [])
        <div class="lms-empty">
            <x-filament::icon icon="heroicon-o-photo" />
            <span>{{ __($b.'media.empty') }}</span>
        </div>
    @else
        <div class="lms-lib">
            @foreach ($library['files'] as $file)
                <div class="lms-lib__item" wire:key="lib-{{ $file['id'] }}">
                    <div class="lms-lib__img">
                        @if ($file['kind'] === 'image')
                            <img src="{{ $file['url'] }}" alt="{{ $file['alt'] ?? '' }}" loading="lazy" decoding="async">
                        @else
                            <video src="{{ $file['url'] }}#t=0.5" preload="metadata" muted playsinline></video>
                        @endif
                    </div>
                    <span class="lms-lib__badge" @if ($file['where'] !== []) data-used title="{{ implode('، ', $file['where']) }}" @endif>
                        {{ $file['where'] !== [] ? __($b.'media.in_use_badge') : __($b.'media.unused_badge') }}
                    </span>
                    @if ($file['kind'] === 'video')
                        <span class="lms-lib__kind" aria-hidden="true"><x-filament::icon icon="heroicon-m-play" /></span>
                    @endif
                    <div class="lms-lib__body">
                        <span class="lms-lib__name" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                        <span class="lms-lib__meta">{{ $file['meta'] }}</span>
                    </div>
                    <div class="lms-lib__tools">
                        @if ($file['kind'] === 'image')
                            {{ ($this->editMediaAction)(['media' => $file['id']]) }}
                        @endif
                        {{ ($this->deleteMediaAction)(['media' => $file['id']]) }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
