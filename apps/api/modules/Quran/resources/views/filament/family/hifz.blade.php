@php $h = 'quran::hifz.'; $d = $this->hifz; $q = $this->quran; $l = 'learning::family.homework.'; $f = 'quran::screens.family.'.($q['kids'] ? 'kids.' : ''); @endphp
<x-filament-panels::page>
    @include('learning::filament.family.styles')
    @include('quran::filament.parts.styles')
    <style>
        .fq-stars { display: inline-flex; gap: .1rem; color: var(--warning-500); font-size: 1.05rem; letter-spacing: .05em; }
        .fq-stars i { font-style: normal; color: var(--gray-300); }
        .fq-part { display: grid; grid-template-columns: .55rem minmax(0, 1fr); gap: .5rem; align-items: baseline; font-size: .9rem; }
        .fq-part > i { inline-size: .55rem; block-size: .55rem; border-radius: 999px; }
        .fq-part[data-part="new"] > i { background: var(--primary-500); }
        .fq-part[data-part="near"] > i { background: var(--info-500); }
        .fq-part[data-part="far"] > i { background: var(--warning-500); }
        .fq-cert { display: flex; gap: .75rem; align-items: center; padding: .8rem .9rem; border-radius: .9rem; border: 1px solid var(--warning-200); background: var(--warning-50); color: inherit; text-decoration: none; }
        .fq-cert svg { inline-size: 1.8rem; block-size: 1.8rem; color: var(--warning-600); flex: none; }
        .dark .fq-stars i { color: var(--gray-600); }
        .dark .fq-cert { background: color-mix(in oklab, var(--warning-500) 12%, transparent); border-color: color-mix(in oklab, var(--warning-500) 30%, transparent); }
    </style>
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
        @foreach ($q['courses'] as $c)
            @php $plan = $c['plan']; $portion = $plan['portion']; @endphp
            <section class="lf-card" aria-labelledby="fq-{{ $loop->index }}">
                <div class="lf-head">
                    <div>
                        <h3 id="fq-{{ $loop->index }}">{{ $q['kids'] ? __($f.'journey') : $plan['program']['name'] }}</h3>
                        <small class="lf-muted">{{ $c['subject'] }}</small>
                    </div>
                    @unless ($q['kids'])<span class="lf-pill">{{ __($f.'progress', ['n' => (int) round(100 * $plan['progress'])]) }}</span>@endunless
                </div>
                @include('quran::filament.parts.strip', ['parts' => $q['parts'], 'shares' => $q['shares'], 'at' => $c['juz'], 'height' => '1.4rem', 'legend' => ! $q['kids'],
                    'label' => __($f.'strip')])
                <p style="margin: 0">{{ $plan['finished'] ? __($f.'finished') : __($f.'where', ['where' => $plan['position']['title'] ?? '']) }}</p>
                @if ($plan['frozen_label'])<div class="qs-banner" role="status"><x-filament::icon icon="heroicon-o-pause-circle" /><span>{{ $plan['frozen_label'] }}</span></div>@endif
                @if ($portion && ! $plan['finished'])
                    <div>
                        <h4 style="font-size: .85rem; font-weight: 700; margin: 0 0 .35rem">{{ __($f.'next') }}</h4>
                        <div style="display: flex; flex-direction: column; gap: .3rem">
                            @if ($portion['lesson'])<div class="fq-part" data-part="new"><i></i><span><strong>{{ $portion['lesson']['title'] }}</strong></span></div>@endif
                            @foreach (['new', 'near', 'far'] as $part)
                                @if ($portion[$part])
                                    <div class="fq-part" data-part="{{ $part }}"><i></i><span><span class="lf-muted">{{ __($f.'parts.'.$part) }}:</span> <strong>{{ collect($portion[$part]['ranges'])->pluck('title')->implode('، ') }}</strong></span></div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($c['recent'] !== [])
                    <div>
                        <h4 style="font-size: .85rem; font-weight: 700; margin: 0 0 .35rem">{{ __($f.'recent') }}</h4>
                        <div class="qs-row">
                            @foreach ($c['recent'] as $r)
                                @php $g = $r['parts']['new']['grade'] ?? ($r['parts']['far']['grade'] ?? ($r['parts']['near']['grade'] ?? 'repeat')); @endphp
                                @if ($q['kids'])
                                    @php $n = \Modules\Quran\Filament\Family\FamilyHifz::stars($g); @endphp
                                    <span class="fq-stars" title="{{ __($f.'grades.'.$g) }}" aria-label="{{ __($f.'grades.'.$g) }}">{!! str_repeat('★', $n) !!}<i>{!! str_repeat('★', 3 - $n) !!}</i></span>
                                @else
                                    <span class="qs-pill" data-tone="{{ in_array($g, ['excellent', 'very_good'], true) ? 'success' : ($g === 'repeat' ? 'warning' : 'primary') }}" title="{{ $r['recorded_at']['label'] }}">{{ __('quran::programs.grades.'.$g) }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endforeach

        @if ($q['exams'] !== [])
            <section class="lf-card" aria-labelledby="fq-exams">
                <div class="lf-head"><h3 id="fq-exams">{{ __($f.'exams') }}</h3></div>
                @foreach ($q['exams'] as $e)
                    <div class="qs-between" style="padding: .45rem 0; border-bottom: 1px solid var(--gray-100)">
                        <div>
                            <strong style="font-size: .9rem">{{ $e['stop']['label'] }}</strong>
                            <div class="lf-muted" style="font-size: .8rem">
                                @if ($e['status'] === 'booked' && $e['lesson']){{ $e['lesson']['starts_at']['label'] }}@if ($e['examiner']) · {{ $e['examiner']['name'] }}@endif
                                @elseif ($e['result'] && $e['result']['note']){{ $e['result']['note'] }}
                                @elseif ($e['status'] === 'suggested'){{ __($f.'exam_soon') }}@endif
                            </div>
                        </div>
                        @if ($e['result'] && $q['kids'])
                            @php $n = \Modules\Quran\Filament\Family\FamilyHifz::stars($e['result']['grade']); @endphp
                            <span class="fq-stars" aria-label="{{ __($f.'grades.'.$e['result']['grade']) }}">{!! str_repeat('★', $n) !!}<i>{!! str_repeat('★', 3 - $n) !!}</i></span>
                        @else
                            <span class="qs-pill" data-tone="{{ ['suggested' => 'warning', 'booked' => 'info', 'passed' => 'success', 'failed' => 'danger'][$e['status']] ?? 'gray' }}">{{ $e['result'] ? $e['result']['grade_label'] : $e['status_label'] }}</span>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        @if ($q['certificates'] !== [])
            <section class="lf-card" aria-labelledby="fq-certs">
                <div class="lf-head"><h3 id="fq-certs">{{ __($f.'certificates') }}</h3></div>
                @foreach ($q['certificates'] as $c)
                    <a class="fq-cert lms-lift" href="{{ $c['url'] }}" target="_blank" rel="noopener">
                        <x-filament::icon icon="heroicon-o-trophy" />
                        <div style="min-width: 0">
                            <strong>{{ $c['portion']['label'] }}</strong>
                            <div class="lf-muted" style="font-size: .8rem">{{ $q['kids'] ? __($f.'well_done') : $c['grade_label'].' · '.$c['issued_at']['label'] }}</div>
                        </div>
                    </a>
                @endforeach
            </section>
        @endif

        @if ($d['latest'])
            <section class="lf-card">
                <div class="lf-head"><h3>{{ __($h.'page', ['page' => $d['latest']['page']]) }}</h3>@if ($d['latest']['grade_label'])<span class="lf-pill">{{ $d['latest']['grade_label'] }}</span>@endif</div>
                @include('quran::filament.teacher.recitation-check', ['check' => $d['latest']])
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

        @if ($d['map']['pages_total'] > 0)
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
            @php $learner = \Modules\Quran\Filament\Family\FamilyQuran::learner(); @endphp
            <div class="lf-head"><h3>{{ $learner && ! $learner->isSelf ? __($h.'title_of', ['name' => explode(' ', trim($learner->name))[0]]) : __($h.'title') }}</h3></div>
            @include('quran::filament.hifz.map', ['map' => $d['map']])
        </section>
        @endif

        @php $kids = \Modules\Learning\Filament\Family\FamilyLearning::stage()->value === 'kids'; $b = 'quran::mushaf.'.($kids ? 'kids.' : ''); @endphp
        @include('quran::filament.mushaf.book', ['book' => $d['book'], 'tz' => $d['tz'], 'kids' => $kids, 'title' => __($b.'book'), 'empty' => __($b.'book_empty')])
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
