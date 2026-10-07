@php
    $l = 'import::import.';
    $run = $this->current;
    $step = $this->step();
    $steps = ['file', 'columns', 'review', 'done'];
    $at = array_search($step, $steps, true);
    $tone = fn (string $s) => match ($s) { 'new', 'created' => 'new', 'update', 'updated' => 'update', 'error', 'failed' => 'error', default => 'skip' };
@endphp
<x-filament-panels::page>
    @include('import::filament.styles')
    <div class="im-stack">
        <ol class="im-steps" aria-label="{{ __($l.'title') }}">
            @foreach ($steps as $i => $s)
                <li data-state="{{ $i < $at ? 'past' : ($i === $at ? 'now' : 'later') }}"><span>{{ $i < $at ? '✓' : $i + 1 }}</span>{{ __($l.'steps.'.$s) }}</li>
            @endforeach
        </ol>

        @if ($step === 'file')
            <div class="im-kinds lms-stagger">
                @foreach (array_keys(\Modules\Import\Importers\Importers::KINDS) as $i => $kind)
                    <div class="im-base im-kind" @if ($this->kind === $kind) data-on @endif wire:click="pick('{{ $kind }}')" wire:key="kind-{{ $kind }}" role="radio" aria-checked="{{ $this->kind === $kind ? 'true' : 'false' }}" tabindex="0" wire:keydown.enter="pick('{{ $kind }}')">
                        <strong><em>{{ $i + 1 }}</em>{{ __($l.'kinds.'.$kind.'.title') }}</strong>
                        <p>{{ __($l.'kinds.'.$kind.'.hint') }}</p>
                        <button type="button" class="im-link" wire:click.stop="template('{{ $kind }}')">{{ __($l.'template') }} ↓</button>
                    </div>
                @endforeach
            </div>
            <p class="im-note">{{ __($l.'order') }}</p>

            <div class="im-base im-drop">
                <label>
                    <strong>{{ __($l.'file.label') }} · {{ __($l.'kinds.'.$this->kind.'.title') }}</strong>
                    <small>{{ __($l.'file.hint', ['max' => \Modules\Import\Actions\StageImport::MAX_ROWS]) }}</small>
                    <input type="file" wire:model="file" accept=".xlsx,.csv,.txt">
                </label>
                <x-filament::button icon="heroicon-m-arrow-up-tray" wire:click="stage">{{ __($l.'file.upload') }}</x-filament::button>
            </div>

            <section class="im-base im-box">
                <h3>{{ __($l.'history.title') }}</h3>
                @if ($this->history === [])
                    <p>{{ __($l.'history.empty') }}</p>
                @else
                    <div class="im-history">
                        @foreach ($this->history as $h)
                            <div wire:key="h-{{ $h['id'] }}">
                                <span><strong>{{ $h['kind'] }} · {{ $h['file'] }}</strong><small>{{ $h['by'] }} · {{ __($l.'history.states.'.$h['status']) }}</small></span>
                                <span class="im-counts">
                                    @foreach ([...\Modules\Import\Models\ImportRow::DONE, ...\Modules\Import\Models\ImportRow::CHECKED] as $status)@php $n = $h['counts'][$status] ?? 0; @endphp @if ($n > 0)<span class="im-chip" data-tone="{{ $tone($status) }}">{{ __($l.'status.'.$status) }} {{ $n }}</span>@endif @endforeach
                                    <button type="button" wire:click="open('{{ $h['id'] }}')">{{ __($l.'history.open') }}</button>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @elseif ($step === 'columns')
            <section class="im-base im-box">
                <h3>{{ __($l.'columns.title') }} · {{ __($l.'kinds.'.$run->kind.'.title') }}</h3>
                <p>{{ __($l.'file.chosen', ['name' => $run->file_name]) }} · {{ __($l.'columns.hint') }}</p>
                <div class="im-map">
                    @foreach ($this->fields() as $field)
                        <div wire:key="map-{{ $field['key'] }}">
                            <label for="map-{{ $field['key'] }}">
                                <span>{{ $field['label'] }} @if ($field['required'])<span class="im-req">· {{ __($l.'columns.required') }}</span>@endif</span>
                                @if ($field['hint'])<small>{{ $field['hint'] }}</small>@endif
                            </label>
                            <select id="map-{{ $field['key'] }}" wire:model="mapping.{{ $field['key'] }}">
                                <option value="">{{ __($l.'columns.none') }}</option>
                                @foreach ($this->columnOptions as $i => $title)<option value="{{ $i }}">{{ $title }}</option>@endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <div class="im-opts">
                    <label class="im-code">{{ __($l.'columns.calling_code') }} <input type="text" inputmode="numeric" wire:model="callingCode" placeholder="20"></label>
                    @if ($run->kind !== 'schedules')
                        <label class="im-check"><input type="checkbox" wire:model="invite"><span>{{ __($l.'columns.invite') }}<small>{{ __($l.'columns.invite_hint') }}</small></span></label>
                    @endif
                </div>
                <div class="im-actions">
                    <x-filament::button wire:key="act-check" wire:click="check" icon="heroicon-m-magnifying-glass" wire:loading.attr="disabled" wire:target="check">{{ __($l.'columns.check') }}</x-filament::button>
                    <x-filament::button wire:key="act-over" color="gray" wire:click="open(null)">{{ __($l.'start_over') }}</x-filament::button>
                </div>
            </section>
        @else
            @php $counts = $run->counts; $done = $step === 'done'; $ready = ($counts['new'] ?? 0) + ($counts['update'] ?? 0); @endphp
            <section class="im-base im-box">
                <h3>{{ $done ? __($l.'done.title', ['file' => $run->file_name]) : __($l.'review.title') }}</h3>
                @unless ($done)<p>{{ __($l.'review.hint') }}</p>@endunless
                <div class="im-tiles">
                    @foreach ($done ? \Modules\Import\Models\ImportRow::DONE : \Modules\Import\Models\ImportRow::CHECKED as $status)
                        @php $n = $counts[$status] ?? 0; @endphp
                        <button type="button" class="im-base im-tile" data-tone="{{ $tone($status) }}" @if ($this->show === $status) data-on @endif wire:click="filter('{{ $this->show === $status ? 'all' : $status }}')" wire:key="t-{{ $status }}">
                            <small>{{ __($l.'status.'.$status) }}</small><strong>{{ $n }}</strong>
                        </button>
                    @endforeach
                </div>
                <div class="im-actions">
                    @if (! $done)
                        <x-filament::button wire:key="act-import" wire:click="import" wire:confirm="{{ __($l.'review.confirm') }}" icon="heroicon-m-check" :disabled="$ready === 0" wire:loading.attr="disabled" wire:target="import">{{ trans_choice($l.'review.import', $ready, ['count' => $ready]) }}</x-filament::button>
                        <x-filament::button wire:key="act-edit" color="gray" wire:click="editColumns">{{ __($l.'review.edit') }}</x-filament::button>
                    @else
                        <x-filament::button wire:key="act-again" wire:click="open(null)" icon="heroicon-m-arrow-up-tray">{{ __($l.'done.again') }}</x-filament::button>
                    @endif
                    @if (($counts['error'] ?? 0) + ($counts['failed'] ?? 0) > 0)
                        <x-filament::button wire:key="act-problems" color="gray" icon="heroicon-m-arrow-down-tray" wire:click="problems">{{ __($l.'review.problems') }}</x-filament::button>
                    @endif
                </div>
            </section>

            <section class="im-base im-rows">
                @foreach ($this->rows as $row)
                    <div class="im-row" data-tone="{{ $tone($row['status']) }}" wire:key="r-{{ $row['line'] }}">
                        <span class="im-line">{{ __($l.'line', ['line' => $row['line']]) }}</span>
                        <div><strong>{{ $row['label'] !== '' ? $row['label'] : '—' }}</strong>@if ($row['message'])<p>{{ $row['message'] }}</p>@endif</div>
                        <span class="im-chip" data-tone="{{ $tone($row['status']) }}">{{ __($l.'status.'.$row['status']) }}</span>
                    </div>
                @endforeach
                @if ($this->shownTotal > count($this->rows))<p class="im-note" style="padding: .6rem 0">{{ __($l.'more', ['count' => $this->shownTotal - count($this->rows)]) }}</p>@endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
