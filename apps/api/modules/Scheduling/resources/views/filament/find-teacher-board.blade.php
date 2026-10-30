{{--
    The week × 24 hours board of Find a teacher, on the student's clock: day
    headers and hours stay in sight while it scrolls, night hours (00–06) are
    shaded and folded until opened, a drag marks (or clears) a whole stretch,
    a tap or Enter one hour, the arrows move between hours. With $heat, each
    hour also says how many teachers are free then. On a phone, one day at a
    time. Needs: $heat (cells or null), $cells, $days, $short, $label.
--}}
@php
    use Modules\SharedKernel\Time\Clock;

    $nightHours = range(0, 5);
    $max = $heat ? max(1, ...array_map(fn ($day) => max([0, ...array_column($day, 'count')]), $heat ?: [[]])) : 1;
    $isMarked = fn (int $day, int $hour) => in_array($hour, $cells[$day] ?? [], true);
    $nightMarked = collect($cells)->contains(fn ($hours) => array_intersect($hours, $nightHours) !== []);
    $nightFree = $heat && collect($heat)->contains(fn ($hours) => array_intersect(array_keys($hours), $nightHours) !== []);
    // The phone's day: the first one with a marked hour, else the first of the week.
    $firstDay = collect($days)->search(fn ($d) => ! empty($cells[$d])) ?: 0;
    // One cell takes the Tab key; the arrows reach the others.
    $anchor = collect($days)->flatMap(fn ($d, $ci) => collect($cells[$d] ?? [])->map(fn ($h) => [$ci, $h]))->sortBy(fn ($p) => $p[1] * 10 + $p[0])->first() ?? [0, 17];
@endphp

<div class="ft-board-wrap" wire:key="board-{{ $heat ? 'heat' : 'plain' }}"
    x-data="{
        night: @js($nightMarked),
        day: @js($firstDay),
        drag: null,
        pointer: null,
        cols: @js(array_values($days)),
        cell(e) { return e.target.closest('[data-h]') },
        start(e) {
            this.pointer = e.pointerType
            const c = this.cell(e)
            if (! c || e.pointerType === 'touch' || e.button !== 0) return
            e.preventDefault()
            c.focus({ preventScroll: true })
            this.drag = { ci0: +c.dataset.ci, h0: +c.dataset.h, ci1: +c.dataset.ci, h1: +c.dataset.h, on: c.getAttribute('aria-pressed') !== 'true' }
            this.preview()
        },
        move(e) {
            if (! this.drag) return
            const c = document.elementFromPoint(e.clientX, e.clientY)?.closest('[data-h]')
            if (c && this.$refs.board.contains(c) && (+c.dataset.ci !== this.drag.ci1 || +c.dataset.h !== this.drag.h1)) {
                this.drag.ci1 = +c.dataset.ci
                this.drag.h1 = +c.dataset.h
                this.preview()
            }
        },
        inside(c) {
            const d = this.drag, ci = +c.dataset.ci, h = +c.dataset.h
            return ci >= Math.min(d.ci0, d.ci1) && ci <= Math.max(d.ci0, d.ci1) && h >= Math.min(d.h0, d.h1) && h <= Math.max(d.h0, d.h1) && c.offsetParent !== null
        },
        preview() {
            this.$refs.board.querySelectorAll('[data-h]').forEach(c => this.drag && this.inside(c) ? c.dataset.drag = this.drag.on ? 'on' : 'off' : delete c.dataset.drag)
        },
        end() {
            if (! this.drag) return
            const picked = [...this.$refs.board.querySelectorAll('[data-h]')].filter(c => this.inside(c))
            const on = this.drag.on
            this.drag = null
            picked.forEach(c => { delete c.dataset.drag; c.setAttribute('aria-pressed', on ? 'true' : 'false') })
            if (picked.length) this.$wire.paint(picked.map(c => [+c.dataset.d, +c.dataset.h]), on)
        },
        tap(e) {
            const c = this.cell(e)
            if (! c || (e.detail > 0 && (this.pointer === 'mouse' || this.pointer === 'pen'))) return
            c.setAttribute('aria-pressed', c.getAttribute('aria-pressed') === 'true' ? 'false' : 'true')
            this.$wire.toggleCell(+c.dataset.d, +c.dataset.h)
        },
        key(e) {
            const c = this.cell(e)
            const steps = { ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [document.dir === 'rtl' ? 1 : -1, 0], ArrowRight: [document.dir === 'rtl' ? -1 : 1, 0] }
            if (! c || ! steps[e.key]) return
            e.preventDefault()
            let [ci, h] = [+c.dataset.ci, +c.dataset.h]
            for (let i = 0; i < 24; i++) {
                ci += steps[e.key][0]; h += steps[e.key][1]
                if (ci < 0 || ci > 6 || h < 0 || h > 23) return
                if (steps[e.key][0] !== 0 && window.matchMedia('(max-width: 639px)').matches) this.day = ci
                const next = this.$refs.board.querySelector(`[data-ci='${ci}'][data-h='${h}']`)
                if (next && (next.offsetParent !== null || steps[e.key][0] !== 0)) { this.$nextTick(() => next.focus()); return }
            }
        },
    }"
    x-on:pointermove.window="move($event)" x-on:pointerup.window="end()" x-on:pointercancel.window="drag = null; preview()">

    <div class="ft-board-tools">
        <button type="button" class="ft-night-toggle" x-on:click="night = ! night" x-bind:aria-expanded="night ? 'true' : 'false'">
            <x-filament::icon icon="heroicon-m-moon" class="ft-ico" />
            <span x-show="! night">{{ __('scheduling::find.board.night_show', ['from' => Clock::wall('00:00'), 'to' => Clock::wall('06:00')]) }}</span>
            <span x-show="night" x-cloak>{{ __('scheduling::find.board.night_hide') }}</span>
            @if ($nightFree)
                <span class="ft-night-free" x-show="! night"><i aria-hidden="true"></i>{{ __('scheduling::find.board.night_free') }}</span>
            @endif
        </button>

        <div class="ft-dayswitch" role="group" aria-label="{{ __('scheduling::find.board.day') }}">
            <button type="button" x-on:click="day = (day + 6) % 7" aria-label="{{ __('scheduling::find.board.prev_day') }}"><x-filament::icon icon="heroicon-m-chevron-left" class="ft-ico" /></button>
            <select x-model.number="day" aria-label="{{ __('scheduling::find.board.day') }}">
                @foreach ($days as $ci => $day)
                    <option value="{{ $ci }}">{{ $this->dayName($day) }}{{ ! empty($cells[$day]) ? ' •' : '' }}</option>
                @endforeach
            </select>
            <button type="button" x-on:click="day = (day + 1) % 7" aria-label="{{ __('scheduling::find.board.next_day') }}"><x-filament::icon icon="heroicon-m-chevron-right" class="ft-ico" /></button>
        </div>
    </div>

    <div class="ft-board-scroll">
        <div class="ft-board" x-ref="board" role="group" aria-label="{{ $label }}" wire:ignore.self
            x-bind:data-day="day" x-bind:class="{ 'is-night': night, 'is-dragging': drag }" data-heat="{{ $heat ? 'true' : 'false' }}"
            x-on:pointerdown="start($event)" x-on:click="tap($event)" x-on:keydown="key($event)">
            <span class="ft-corner" aria-hidden="true"></span>
            @foreach ($days as $ci => $day)
                <span class="ft-colhead" data-col="{{ $ci }}" @if (! empty($cells[$day])) data-has="true" @endif>
                    <span class="ft-colhead-long">{{ $this->dayName($day) }}</span><span class="ft-colhead-short" aria-hidden="true">{{ $short($day) }}</span>
                </span>
            @endforeach

            @foreach (range(0, 23) as $hour)
                @php $night = $hour < 6; @endphp
                <span class="ft-rowhead" @if ($night) data-night @endif @if ($hour === 6) data-dawn @endif aria-hidden="true">{{ Clock::hour($hour) }}</span>
                @foreach ($days as $ci => $day)
                    @php
                        $on = $isMarked($day, $hour);
                        $cell = $heat[$day][$hour] ?? null;
                        $count = $cell['count'] ?? 0;
                        $level = ! $heat || $count === 0 ? 0 : max(1, (int) ceil(4 * $count / $max));
                        $names = $cell ? implode('، ', $cell['names']).($count > count($cell['names']) ? ' '.__('scheduling::find.free.others', ['n' => $count - count($cell['names'])]) : '') : '';
                        $aria = $this->dayName($day).' '.Clock::hour($hour).'–'.Clock::hour($hour + 1)
                            .($heat ? ' · '.($count === 0 ? __('scheduling::find.free.nobody') : trans_choice('scheduling::find.free.teachers', $count, ['count' => $count]).': '.$names) : '');
                    @endphp
                    <button type="button" class="ft-c" data-d="{{ $day }}" data-h="{{ $hour }}" data-ci="{{ $ci }}" data-col="{{ $ci }}" data-level="{{ $level }}"
                        @if ($night) data-night @endif @if ($hour === 6) data-dawn @endif
                        tabindex="{{ [$ci, $hour] === $anchor ? 0 : -1 }}"
                        aria-pressed="{{ $on ? 'true' : 'false' }}" aria-label="{{ $aria }}" @if ($heat) title="{{ $aria }}" @endif
                        wire:key="c-{{ $day }}-{{ $hour }}">@if ($heat && $count > 0)<span>{{ $count }}</span>@endif</button>
                @endforeach
            @endforeach
        </div>
    </div>

    <div class="ft-legend" aria-hidden="true">
        @if ($heat)
            <span>{{ __('scheduling::find.free.fewer') }}</span>
            @foreach ([1, 2, 3, 4] as $level)
                <i class="ft-swatch" data-level="{{ $level }}"></i>
            @endforeach
            <span>{{ __('scheduling::find.free.more') }}</span>
            <span class="ft-legend-gap"></span>
        @endif
        <i class="ft-swatch" data-marked></i><span>{{ __('scheduling::find.board.marked') }}</span>
        <span class="ft-legend-gap"></span>
        <i class="ft-swatch" data-night></i><span>{{ __('scheduling::find.board.night') }}</span>
    </div>
</div>
