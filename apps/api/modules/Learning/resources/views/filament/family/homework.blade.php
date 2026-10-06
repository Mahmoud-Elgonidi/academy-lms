@php
    $l = 'learning::family.homework.';
    $h = 'learning::homework.';
    $data = $this->homework;
    $at = fn ($t) => \Modules\Learning\Filament\Family\FamilyLearning::date($t, $data['tz'], 'l j F · H:i');
@endphp
<x-filament-panels::page>
    @include('learning::filament.family.styles')
    <style>
        .lf-form { display: flex; flex-direction: column; gap: .6rem; }
        .lf-form textarea { width: 100%; min-height: 6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-lg, .5rem); padding: .6rem .7rem; font: inherit; background: transparent; }
        .lf-rec { display: flex; flex-direction: column; gap: .5rem; }
        .lf-rec-row { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .lf-rec-btn { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .9rem; border-radius: 999px; border: 1px solid var(--gray-300); background: white; font-weight: 600; cursor: pointer; }
        .lf-rec-btn[data-on] { border-color: var(--danger-500); color: var(--danger-700); background: var(--danger-50); }
        .lf-rec-dot { width: .75rem; height: .75rem; border-radius: 999px; background: var(--danger-600); }
        .lf-rec-stop { width: .7rem; height: .7rem; border-radius: 2px; background: var(--danger-600); }
        .lf-err { color: var(--danger-600); font-size: .85rem; }
        .lf-file { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; font-size: .9rem; }
        .lf-due[data-late] { color: var(--danger-600); font-weight: 600; }
        .lf-h2 { font-size: 1rem; font-weight: 700; margin: .5rem 0 0; }
        .dark .lf-rec-btn { background: var(--gray-900); border-color: var(--gray-700); }
    </style>

    <div class="lf-stack lms-stagger">
        @forelse ($data['open'] as $hw)
            @php $late = $hw->status === 'missed' || $hw->due_at->isPast(); @endphp
            <article class="lf-card" wire:key="hw-{{ $hw->id }}">
                <div class="lf-head">
                    <div>
                        <h3>{{ $hw->title }}</h3>
                        <small>{{ __($l.'from', ['teacher' => $data['teachers'][$hw->teacher_id] ?? '—']) }} · {{ __($h.'accepts.'.$hw->accepts) }}</small>
                    </div>
                    <small class="lf-due" @if ($late) data-late @endif>{{ __($l.($late ? 'was_due' : 'due'), ['date' => $at($hw->due_at)]) }}</small>
                </div>
                @if ($hw->instructions)
                    <p class="lf-note">{{ $hw->instructions }}</p>
                @endif

                <form class="lf-form" wire:submit="handIn('{{ $hw->id }}')">
                    @if (in_array($hw->accepts, ['any', 'text'], true))
                        <textarea wire:model="answers.{{ $hw->id }}" placeholder="{{ __($l.'answer') }}" aria-label="{{ __($h.'attributes.body') }}"></textarea>
                        @error('answers.'.$hw->id) <small class="lf-err">{{ $message }}</small> @enderror
                    @endif
                    @if (in_array($hw->accepts, ['any', 'audio'], true))
                        @include('learning::filament.family.recorder', ['id' => $hw->id])
                    @endif
                    @if ($hw->accepts !== 'text')
                        <div class="lf-file">
                            @if (($files[$hw->id] ?? null) && ! str_starts_with((string) $files[$hw->id]->getMimeType(), 'audio') && ! str_starts_with((string) $files[$hw->id]->getMimeType(), 'video'))
                                <span>📎 {{ $files[$hw->id]->getClientOriginalName() }}</span>
                                <x-filament::link tag="button" type="button" wire:click="removeFile('{{ $hw->id }}')" color="danger" size="sm">{{ __($l.'remove') }}</x-filament::link>
                            @else
                                <label class="lf-rec-btn">
                                    <input type="file" hidden wire:model="files.{{ $hw->id }}" accept="{{ $hw->accepts === 'audio' ? 'audio/*' : ($hw->accepts === 'file' ? 'image/*,application/pdf' : 'audio/*,image/*,application/pdf') }}">
                                    📎 {{ __($hw->accepts === 'audio' ? $l.'or_audio_file' : $l.'or_file') }}
                                </label>
                                <small class="lf-muted" wire:loading wire:target="files.{{ $hw->id }}">{{ __($l.'uploading') }}</small>
                            @endif
                        </div>
                        @error('files.'.$hw->id) <small class="lf-err">{{ $message }}</small> @enderror
                    @endif
                    <div>
                        <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:target="handIn('{{ $hw->id }}'), files.{{ $hw->id }}">{{ __($l.'hand_in') }}</x-filament::button>
                    </div>
                </form>
            </article>
        @empty
            <div class="lf-empty">{{ __($l.'none_open') }}</div>
        @endforelse

        @if ($data['done']->isNotEmpty())
            <h2 class="lf-h2">{{ __($l.'handed_in') }}</h2>
            @foreach ($data['done'] as $hw)
                <article class="lf-card" wire:key="hw-done-{{ $hw->id }}">
                    <div class="lf-head">
                        <div>
                            <h3>{{ $hw->title }}</h3>
                            <small>{{ __($l.'from', ['teacher' => $data['teachers'][$hw->teacher_id] ?? '—']) }}</small>
                        </div>
                        <span class="lf-pill" data-tone="{{ $hw->status === 'reviewed' ? 'good' : '' }}">{{ __($h.'statuses.'.$hw->status) }}@if ($hw->grade) · {{ $hw->grade }}/5 @endif</span>
                    </div>
                    @include('learning::filament.teacher.submissions', ['homework' => $hw, 'at' => $at])
                </article>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
