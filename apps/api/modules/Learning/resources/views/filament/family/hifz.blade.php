@php $h = 'learning::hifz.'; $d = $this->hifz; $l = 'learning::family.homework.'; @endphp
<x-filament-panels::page>
    @include('learning::filament.family.styles')
    <style>
        .lf-rec { display: flex; flex-direction: column; gap: .5rem; }
        .lf-rec-row { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .lf-rec-btn { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .9rem; border-radius: 999px; border: 1px solid var(--gray-300); background: white; font-weight: 600; cursor: pointer; }
        .lf-rec-btn[data-on] { border-color: var(--danger-500); color: var(--danger-700); background: var(--danger-50); }
        .lf-rec-dot { width: .75rem; height: .75rem; border-radius: 999px; background: var(--danger-600); }
        .lf-rec-stop { width: .7rem; height: .7rem; border-radius: 2px; background: var(--danger-600); }
        .lf-err { color: var(--danger-600); font-size: .85rem; }
        .lf-mushaf { margin: 0; font-size: 1.15rem; line-height: 2.2; }
        .lf-mushaf span { color: var(--gray-400); font-size: .75em; }
        .dark .lf-rec-btn { background: var(--gray-900); border-color: var(--gray-700); }
    </style>
    <div data-live-channel="{{ $d['channel'] }}" hidden></div>
    <div class="lf-stack lms-stagger">
        @if ($d['latest'])
            <section class="lf-card">
                <div class="lf-head"><h3>{{ __($h.'page', ['page' => $d['latest']['page']]) }}</h3>@if ($d['latest']['grade_label'])<span class="lf-pill">{{ $d['latest']['grade_label'] }}</span>@endif</div>
                @include('learning::filament.teacher.recitation-check', ['check' => $d['latest']])
            </section>
        @endif

        @if ($d['page'])
            {{-- A page open: recite it from memory, then see it, then say how it went (or record it to be checked). --}}
            <section class="lf-card" wire:key="page-{{ $d['page']['page'] }}">
                <div class="lf-head">
                    <div><h3>{{ $d['page']['title'] }}</h3><small class="lf-muted">{{ __($h.'strengths.'.$d['page']['strength']) }}</small></div>
                    <x-filament::button color="gray" size="sm" wire:click="closePage">{{ __($h.'close') }}</x-filament::button>
                </div>
                @if ($d['mode'] === 'check')
                    <p class="lf-muted" style="margin:0">{{ $this->word('recite_hint') }}</p>
                    <div x-data="{
                            rec: null, chunks: [], recording: false, secs: 0, timer: null, failed: false, busy: false,
                            clock() { return Math.floor(this.secs / 60) + ':' + String(this.secs % 60).padStart(2, '0') },
                            async start() {
                                this.failed = false;
                                try {
                                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                                    const type = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'].find((t) => MediaRecorder.isTypeSupported(t));
                                    this.rec = new MediaRecorder(stream, type ? { mimeType: type } : {});
                                    this.chunks = [];
                                    this.rec.ondataavailable = (e) => { if (e.data.size) this.chunks.push(e.data) };
                                    this.rec.onstop = () => {
                                        stream.getTracks().forEach((t) => t.stop());
                                        const mime = (this.rec.mimeType || 'audio/webm').split(';')[0];
                                        const file = new File(this.chunks, 'page.' + (mime.includes('mp4') ? 'm4a' : 'webm'), { type: mime });
                                        this.busy = true;
                                        $wire.upload('recording', file, () => $wire.recite().finally(() => { this.busy = false }), () => { this.busy = false; this.failed = true });
                                    };
                                    this.rec.start();
                                    this.recording = true; this.secs = 0;
                                    this.timer = setInterval(() => { this.secs++; if (this.secs >= 300) this.stop() }, 1000);
                                } catch (e) { this.failed = true }
                            },
                            stop() { clearInterval(this.timer); this.recording = false; this.rec?.stop() },
                        }" class="lf-rec">
                        <div class="lf-rec-row">
                            <button type="button" class="lf-rec-btn" x-show="! recording && ! busy" x-on:click="start()"><span class="lf-rec-dot"></span>{{ $this->word('recite') }}</button>
                            <button type="button" class="lf-rec-btn" data-on x-show="recording" x-cloak x-on:click="stop()"><span class="lf-rec-stop"></span>{{ __($l.'stop') }} <span x-text="clock()"></span></button>
                            <small class="lf-muted" x-show="busy" x-cloak>{{ __($h.'hearing') }}</small>
                        </div>
                        <small class="lf-err" x-show="failed" x-cloak>{{ __($l.'mic_failed') }}</small>
                    </div>
                @endif
                @if ($revealed)
                    <p class="lf-mushaf" dir="rtl" lang="ar">
                        @foreach ($d['page']['verses'] as $v){{ $v['text'] }} <span>({{ $v['verse'] }})</span> @endforeach
                    </p>
                    <small class="lf-muted">{{ $d['page']['attribution'] }}</small>
                @else
                    <div><x-filament::button color="gray" wire:click="reveal">{{ $this->word('reveal') }}</x-filament::button></div>
                @endif
                <div>
                    <p class="lf-muted" style="margin:0 0 .4rem">{{ $this->word($d['mode'] === 'check' ? 'or_grade' : 'how_went') }}</p>
                    <div style="display:flex; flex-wrap:wrap; gap:.4rem">
                        @foreach ([1 => 'danger', 2 => 'warning', 3 => 'primary', 4 => 'success'] as $g => $color)
                            <x-filament::button :color="$color" size="sm" wire:click="grade({{ $g }})">{{ $this->word('grades.'.$g) }}</x-filament::button>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="lf-card">
            <div class="lf-head">
                <h3>{{ $this->word('wird') }}</h3>
                <span class="lf-pill">{{ __($h.'wird_done', ['done' => $d['wird']['done_today'], 'daily' => $d['wird']['daily']]) }}</span>
            </div>
            @forelse ($d['wird']['pages'] as $p)
                <button type="button" class="lf-card lms-lift" style="text-align:start; display:flex; justify-content:space-between; gap:.5rem; width:100%" wire:click="openPage({{ $p['page'] }})">
                    <span>{{ $p['title'] }}</span>
                    <small class="lf-muted">{{ __($h.'strengths.'.$p['strength']) }}</small>
                </button>
            @empty
                <div class="lf-empty">{{ $this->word('wird_empty') }}</div>
            @endforelse
        </section>

        <section class="lf-card">
            <div class="lf-head"><h3>{{ __($h.'title') }}</h3></div>
            @include('learning::filament.hifz.map', ['map' => $d['map']])
        </section>

        @php $kids = \Modules\Learning\Filament\Family\FamilyLearning::stage()->value === 'kids'; $b = 'learning::mushaf.'.($kids ? 'kids.' : ''); @endphp
        @include('learning::filament.mushaf.book', ['book' => $d['book'], 'tz' => $d['tz'], 'kids' => $kids, 'title' => __($b.'book'), 'empty' => __($b.'book_empty')])
    </div>
</x-filament-panels::page>

@script
<script>
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.hifz.changed', refresh: () => $wire.$refresh(), every: 120000 });
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.mushaf.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
