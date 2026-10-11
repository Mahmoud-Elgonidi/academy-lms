@php
    /*
     * One game in the studio (the approved design, Studio-Game): basics on one side with content,
     * rules and rewards; the live preview and how it does across academies on the other.
     */
    $l = 'platform::games.';
    $e = $l.'editor.';
    $game = $this->game;
    $perf = $this->performance;
    $rules = $this->rules();
    $weak = $perf['positions'] === [] ? null : collect($perf['positions'])->sort()->keys()->first();
    $fmt = fn (int $s) => $s >= 60 ? intdiv($s, 60).':'.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT) : __($e.'seconds', ['s' => $s]);
@endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::games.styles')
    <div class="ge">
        <header class="ge-head">
            <div style="min-width: 0">
                <nav class="ge-crumbs" aria-label="breadcrumb">
                    <a href="{{ \Modules\Platform\Filament\Console\Pages\GameStudio::getUrl() }}" wire:navigate>{{ __($l.'title') }}</a> ›
                    <span>{{ $game->island?->name() }}</span> › <span>{{ $game->title() }}</span>
                </nav>
                <h1>{{ $game->title() }} <span class="gs-state" data-state="{{ $game->status }}">{{ __($l.'status.'.$game->status) }}@if ($game->current) · {{ __($l.'version', ['n' => $game->current->version]) }}@endif</span></h1>
            </div>
            <div class="ge-actions">
                @if ($game->status === 'live')
                    <x-filament::button color="gray" outlined wire:click="setStatus('retired')" wire:confirm="{{ __($l.'retired_done') }}">{{ __($l.'retire') }}</x-filament::button>
                @elseif ($game->current)
                    <x-filament::button color="success" outlined wire:click="setStatus('live')">{{ __($l.'revive') }}</x-filament::button>
                @endif
                <x-filament::button icon="heroicon-m-sparkles" wire:click="develop">{{ __($e.'develop') }}</x-filament::button>
            </div>
        </header>

        <div class="ge-grid">
            <div class="ge-main">
                <form class="pc-base ge-card" wire:submit="save">
                    <h2>{{ __($e.'basics') }}</h2>
                    <div class="ge-two">
                        <label>{{ __($e.'title_ar') }}<input type="text" wire:model="details.title_ar" maxlength="80" dir="rtl"></label>
                        <label>{{ __($e.'title_en') }}<input type="text" wire:model="details.title_en" maxlength="80" dir="ltr"></label>
                    </div>
                    @error('details.title_ar')<p class="ge-error">{{ $message }}</p>@enderror
                    @error('details.title_en')<p class="ge-error">{{ $message }}</p>@enderror
                    <label>{{ __($e.'summary_ar') }}<textarea rows="2" wire:model="details.summary_ar" maxlength="300" dir="rtl"></textarea></label>
                    <label>{{ __($e.'summary_en') }}<textarea rows="2" wire:model="details.summary_en" maxlength="300" dir="ltr"></textarea></label>
                    <label>{{ __($e.'island') }}
                        <select wire:model="details.island_id">
                            @foreach ($this->islands() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </label>
                    <div>
                        <div class="ge-label">{{ __($e.'stages') }}</div>
                        <div class="ge-pills" role="group" aria-label="{{ __($e.'stages') }}">
                            @foreach ($this->stages() as $stage)
                                <button type="button" aria-pressed="{{ in_array($stage, $details['stages'] ?? [], true) ? 'true' : 'false' }}" wire:click="toggleStage('{{ $stage }}')">{{ __('shared::games.stages.'.$stage) }}</button>
                            @endforeach
                        </div>
                        @error('details.stages')<p class="ge-error">{{ $message }}</p>@enderror
                    </div>
                    <div><x-filament::button type="submit">{{ __($e.'save') }}</x-filament::button></div>
                </form>

                <section class="pc-base ge-card">
                    <h2>{{ __($e.'content') }}</h2>
                    <p class="ge-muted">{{ __($e.'content_hint') }}</p>
                    <p class="ge-chips">@foreach ($this->kinds() as $kind)<span>{{ $kind }}</span>@endforeach</p>
                    <div class="ge-label">{{ __($e.'sample') }}</div>
                    <div class="ge-table" role="region" aria-label="{{ __($e.'sample') }}" tabindex="0">
                        <table>
                            <thead><tr><th>{{ __($e.'col_n') }}</th><th>{{ __($e.'col_item') }}</th><th>{{ __($e.'col_right') }}</th><th>{{ __($e.'col_others') }}</th></tr></thead>
                            <tbody>
                                @foreach ($this->sample as $i => $item)
                                    @php
                                        $kind = $item['kind'] ?? 'choice';
                                        [$prompt, $right, $others] = match ($kind) {
                                            'order' => [$item['prompt'] ?? '', implode(' ← ', $item['parts'] ?? []), '—'],
                                            'pair' => [$item['left'] ?? '', $item['right'] ?? '', '—'],
                                            default => [$item['prompt'] ?? '', $item['options'][$item['answer'] ?? 0] ?? '', implode('، ', array_values(array_diff_key($item['options'] ?? [], [($item['answer'] ?? 0) => true])))],
                                        };
                                    @endphp
                                    <tr wire:key="sample-{{ $item['id'] ?? $i }}">
                                        <td>{{ $i + 1 }}</td>
                                        <td><b>{{ $prompt }}</b> <small>{{ __($e.'kind.'.$kind) }}</small></td>
                                        <td><span class="ge-right">{{ $right }}</span></td>
                                        <td>{{ $others }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="pc-base ge-card">
                    <h2>{{ __($e.'rules') }}</h2>
                    <p class="ge-chips"><span>{{ __($e.'per_round', $rules) }}</span></p>
                    <p class="ge-muted">{{ __($e.'rules_hint') }}</p>
                </section>

                <section class="pc-base ge-card">
                    <h2>{{ __($e.'rewards') }}</h2>
                    <p>⭐ {{ __($e.'stars_rule') }}</p>
                    <p>🥚 {{ __($e.'egg_rule', ['need' => 12]) }}</p>
                </section>
            </div>

            <aside class="ge-side">
                <section class="pc-base ge-card">
                    <h2>{{ __($e.'preview') }}</h2>
                    <div class="gs-stages" role="group" aria-label="{{ __($l.'draft.look') }}">
                        @foreach ($game->stages as $stage)
                            <button type="button" wire:click="setTryStage('{{ $stage }}')" aria-pressed="{{ $this->tryStage === $stage ? 'true' : 'false' }}">{{ __('shared::games.stages.'.$stage) }}</button>
                        @endforeach
                    </div>
                    @if ($game->current)
                        <div class="gs-phone" wire:key="ge-try-{{ $this->tryStage }}">
                            <div class="gs-player" wire:ignore data-game-player data-src="{{ route('games.play', $game->current->id) }}" data-items="{{ json_encode($this->sample, JSON_UNESCAPED_UNICODE) }}"
                                 data-stage="{{ $this->tryStage }}" data-locale="{{ app()->getLocale() === 'ar' ? 'ar' : 'en' }}" data-dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-title="{{ $game->title() }}"></div>
                        </div>
                    @endif
                </section>

                <section class="pc-base ge-card">
                    <h2>{{ __($e.'performance') }}</h2>
                    @if ($perf['rounds'] === 0)
                        <p class="ge-muted">{{ __($e.'none') }}</p>
                    @else
                        <p class="ge-muted">{{ __($e.'academies', ['count' => $perf['academies']]) }}</p>
                        <div class="ge-stats">
                            <div><small>{{ __($e.'rounds') }}</small><b data-count="{{ $perf['rounds'] }}">{{ number_format($perf['rounds']) }}</b></div>
                            <div><small>{{ __($e.'finished') }}</small><b>{{ $perf['finished'] }}%</b></div>
                            <div><small>{{ __($e.'stars') }}</small><b>{{ $perf['stars'] }} / 3</b></div>
                            <div><small>{{ __($e.'time') }}</small><b>{{ $fmt($perf['seconds']) }}</b></div>
                        </div>
                        <div class="ge-label">{{ __($e.'positions') }}</div>
                        <div class="ge-bars">
                            @foreach ($perf['positions'] as $i => $pct)
                                <div><span>{{ __($e.'position', ['n' => $i + 1]) }}</span><i><b class="lms-bar-fill" style="width: {{ $pct }}%" @if ($i === $weak) data-weak @endif></b></i><em>{{ $pct }}%</em></div>
                            @endforeach
                        </div>
                        @if ($weak !== null && $perf['positions'][$weak] < 60)
                            <p class="ge-hint">{{ __($e.'weak', ['n' => $weak + 1, 'pct' => $perf['positions'][$weak]]) }}</p>
                        @endif
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-filament-panels::page>

@script
<script>
    // The preview's player mounts when it appears (a new look renders a new one).
    const mount = () => $wire.$el.querySelectorAll('[data-game-player]').forEach((root) => window.LmsGame?.mount(root));
    new MutationObserver(mount).observe($wire.$el, { childList: true, subtree: true });
    mount();
</script>
@endscript
