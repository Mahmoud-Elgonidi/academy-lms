{{-- Recordings to watch, one playing at a time above the list; each opening is logged. --}}
@php $l = 'classroom::recordings.'; @endphp
@if ($playing)
    <section class="rc-player" wire:key="rc-player-{{ $playing['id'] }}" aria-label="{{ __($l.'office.player') }}">
        <div class="rc-player-top">
            <small>{{ __($l.'office.logged') }}</small>
            <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" wire:click="closePlayer" :label="__($l.'office.close')" />
        </div>
        @if ($playing['media'] === 'audio')
            <audio controls autoplay controlsList="nodownload" src="{{ $playing['url'] }}" x-on:contextmenu.prevent></audio>
        @else
            <video controls autoplay playsinline controlsList="nodownload noplaybackrate" disablePictureInPicture src="{{ $playing['url'] }}" x-on:contextmenu.prevent></video>
        @endif
    </section>
@endif
<div class="rc-list lms-stagger">
    @forelse ($recordings as $r)
        <article class="rc-item lms-lift" wire:key="rc-{{ $r['id'] }}" data-playing="{{ ($playing['id'] ?? null) === $r['id'] ? 'yes' : 'no' }}">
            <div class="rc-item-text">
                <strong>{{ $r['when'] }}</strong>
                <small>{{ $withStudent ? $r['student'].' · ' : '' }}{{ $r['teacher'] }} · {{ __($l.'media.'.$r['media']) }} · {{ \Modules\Classroom\Filament\Pages\RecordingsPage::length($r['duration_sec']) }}@if ($r['until']) · {{ __($l.'family.until', ['date' => $r['until']]) }}@endif</small>
            </div>
            <x-filament::button size="sm" icon="heroicon-o-play" wire:click="play('{{ $r['id'] }}')">{{ __($l.'office.watch') }}</x-filament::button>
        </article>
    @empty
        <div class="rc-empty">{{ $empty }}</div>
    @endforelse
</div>
