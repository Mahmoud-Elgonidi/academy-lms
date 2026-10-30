@php
    $l = 'classroom::classroom.';
    $r = 'classroom::classroom.room.';
    $other = $seat->role === 'teacher' ? $lesson->studentName : $lesson->teacherName;
    $rtl = app()->getLocale() === 'ar';
    $publishes = $seat->role !== 'observer';
    // Our own icons (24×24, stroked), for the bar here and the tiles the script draws.
    $icons = [
        'mic' => 'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3Z M19 11a7 7 0 0 1-14 0 M12 18v3',
        'micOff' => 'M9 9v3a3 3 0 0 0 5.1 2.1 M15 9.3V6a3 3 0 0 0-5.7-1.3 M19 11a7 7 0 0 1-1.1 3.8 M5 11a7 7 0 0 0 11.2 5.6 M12 18v3 M3 3l18 18',
        'cam' => 'M3 7.5A1.5 1.5 0 0 1 4.5 6h9A1.5 1.5 0 0 1 15 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 3 16.5v-9Z M15 10.5l6-3.5v10l-6-3.5',
        'camOff' => 'M15 10.5l6-3.5v10l-6-3.5 M10.5 6h3A1.5 1.5 0 0 1 15 7.5v3 M15 15v1.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 3 16.5v-9A1.5 1.5 0 0 1 4.5 6H6 M3 3l18 18',
        'screen' => 'M3 5.5A1.5 1.5 0 0 1 4.5 4h15A1.5 1.5 0 0 1 21 5.5v10a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 15.5v-10Z M8 21h8 M12 17v4 M12 13V8 M9.5 10.5 12 8l2.5 2.5',
        'pip' => 'M3 6.5A1.5 1.5 0 0 1 4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-11Z M12.5 12.5h5.5v4h-5.5z',
        'auto' => 'M3 5.5A1.5 1.5 0 0 1 4.5 4h15A1.5 1.5 0 0 1 21 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5v-13Z M13 13h5v4h-5z',
        'grid' => 'M4 4h7v7H4z M13 4h7v7h-7z M4 13h7v7H4z M13 13h7v7h-7z',
        'spotlight' => 'M3 5h12v14H3z M17.5 5H21v4h-3.5z M17.5 10H21v4h-3.5z M17.5 15H21v4h-3.5z',
        'panel' => 'M3 5.5A1.5 1.5 0 0 1 4.5 4h15A1.5 1.5 0 0 1 21 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5v-13Z M15 4v16 M17.5 8h1 M17.5 11h1',
        'more' => 'M12 5.5v.01 M12 12v.01 M12 18.5v.01',
        'leave' => 'M3.6 14.6c-.5-.5-.6-1.3-.1-1.9C5.6 10.3 8.7 9 12 9s6.4 1.3 8.5 3.7c.5.6.4 1.4-.1 1.9l-1.6 1.6c-.5.5-1.3.5-1.8.1l-1.6-1.3a1.2 1.2 0 0 1-.4-1.1l.2-1.4a10 10 0 0 0-6.4 0l.2 1.4c.1.4-.1.8-.4 1.1l-1.6 1.3c-.5.4-1.3.4-1.8-.1Z',
        'people' => 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z M2.5 20a6.5 6.5 0 0 1 13 0 M16 4.3a3.5 3.5 0 0 1 0 6.4 M18 14.2a6.5 6.5 0 0 1 3.5 5.8',
        'pin' => 'M9 4h6 M10 4v6l-3 4h10l-3-4V4 M12 14v6',
        'record' => 'M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Z',
        'audio' => 'M4 15v-3a8 8 0 0 1 16 0v3 M4 15a2 2 0 0 1 2-2h1v7H6a2 2 0 0 1-2-2v-3Z M20 15a2 2 0 0 0-2-2h-1v7h1a2 2 0 0 0 2-2v-3Z',
        'flag' => 'M5 21V4 M5 4h11l-2 4 2 4H5',
        'close' => 'M6 6l12 12 M18 6 6 18',
        'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M12 7v5l3 2',
    ];
    $icon = fn (string $name, string $class = '') => '<svg class="ic '.$class.'" viewBox="0 0 24 24" aria-hidden="true"'.($name === 'more' ? ' style="stroke-width:3"' : '').'><path d="'.$icons[$name].'"/></svg>';
    $cfg = [
        'ticketUrl' => $ticketUrl,
        'goUrl' => $goUrl,
        'role' => $seat->role,
        'identity' => $seat->identity,
        'joining' => __($l.'check.joining'),
        'quality' => trans($l.'room.quality'),
        'providerError' => __($l.'errors.provider'),
        'mushaf' => $mushaf !== null,
        'icons' => $icons,
        'words' => [
            'you' => __($r.'you'),
            'micOn' => __($r.'mic_on').' (Ctrl+D)', 'micOff' => __($r.'mic_off').' (Ctrl+D)',
            'camOn' => __($r.'camera_on').' (Ctrl+E)', 'camOff' => __($r.'camera_off').' (Ctrl+E)',
            'screen' => __($r.'screen'), 'screenStop' => __($r.'screen_stop'),
            'screenOf' => __($r.'screen_of'), 'presenting' => __($r.'presenting'),
            'pin' => __($r.'pin'), 'selfView' => __($r.'self_view'), 'leave' => __($r.'leave'), 'muted' => __($r.'muted'),
        ],
        'recording' => $recording + [
            'labels' => ['start' => __('classroom::recordings.room.start'), 'stop' => __('classroom::recordings.room.stop')],
        ],
    ];
    $materials = collect($plan)->flatMap(fn ($line) => $line->materials)->unique('id')->values();
    $hasPanel = $mushaf || $seat->role !== 'student' || $materials->isNotEmpty();
    // Materials' own icons (the panel's list).
    $materialIcons = [
        'pdf' => 'M14 3H7.5A1.5 1.5 0 0 0 6 4.5v15A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V7l-4-4ZM14 3v4h4M9 13h6M9 17h4',
        'audio' => 'M9 18V6l10-2v12M9 18a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm10-2a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z',
        'video' => 'M4.5 6h9A1.5 1.5 0 0 1 15 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 3 16.5v-9A1.5 1.5 0 0 1 4.5 6ZM15 10.5l6-3.5v10l-6-3.5',
        'image' => 'M4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5ZM3 16l5-5 4 4 3-3 6 6M15.5 9.5h.01',
        'link' => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
    ];
    // Anyone in the lesson can tell the academy's safeguarding leads that something is wrong, without leaving it.
    $concernRoute = ['student' => 'filament.family.pages.tell-us', 'teacher' => 'filament.teach.pages.raise-concern'][$seat->role] ?? null;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>{{ __($l.'title') }} · {{ $lesson->subject() }} · {{ $brand->name() }}</title>
    @if ($brand->faviconUrl) <link rel="icon" href="{{ $brand->faviconUrl }}"> @endif
    @include('classroom::room-styles')
    <script src="{{ config('classroom.client_js') }}"></script>
</head>
<body data-state="prejoin">
    <header class="topbar">
        <span class="brand">{{ $brand->name() }}</span>
        <span class="lesson">
            <strong>{{ __($l.'with', ['subject' => $lesson->subject(), 'teacher' => $lesson->teacherName, 'student' => $lesson->studentName]) }}</strong>
            <small>{{ $when }}</small>
        </span>
        <span class="stat timer" id="timer" title="{{ __($r.'timer') }}" dir="ltr" hidden>00:00</span>
        <span class="stat quality" id="quality" role="status" hidden><span class="q-text"></span></span>
        <span class="rec" id="rec" role="status" @if (! $recording['running']) hidden @endif>{{ __('classroom::recordings.room.on') }}</span>
        <span class="stat people" id="people" title="{{ __($r.'people') }}" hidden>{!! $icon('people') !!}<span id="people-n">1</span><span class="sr">{{ __($r.'people') }}</span></span>
        @if ($concernRoute && Route::has($concernRoute))
            <a class="concern" href="{{ route($concernRoute) }}" target="_blank" rel="noopener" title="{{ __($r.'concern') }}">{!! $icon('flag') !!}<span class="label">{{ __($r.'concern') }}</span></a>
        @endif
        <span class="chip">{{ __($l.'roles.'.$seat->role) }}</span>
    </header>

    <main class="{{ $mushaf ? 'with-plan with-mushaf' : ($hasPanel ? 'with-plan' : '') }}">
        <div class="stage">
            <section class="prejoin {{ $publishes ? '' : 'observer' }}" id="prejoin">
                @if ($publishes)
                    <div class="preview">
                        <video id="preview" autoplay muted playsinline></video>
                        <div class="off" id="preview-off" hidden>{{ __($l.'check.camera_paused') }}</div>
                        <div class="meter" title="{{ __($l.'check.mic') }}"><i id="meter"></i></div>
                        <div class="pre-tools">
                            <button type="button" class="round toggle mute" id="pre-mic" aria-pressed="true" title="{{ __($r.'mic_off') }} (Ctrl+D)" aria-label="{{ __($r.'mic_off') }}">{!! $icon('mic', 'on') !!}{!! $icon('micOff', 'off') !!}</button>
                            <button type="button" class="round toggle mute" id="pre-cam" aria-pressed="true" title="{{ __($r.'camera_off') }} (Ctrl+E)" aria-label="{{ __($r.'camera_off') }}">{!! $icon('cam', 'on') !!}{!! $icon('camOff', 'off') !!}</button>
                        </div>
                    </div>
                @endif
                <div class="ready">
                    <h1>{{ __($l.'check.title') }}</h1>
                    <p class="what">{{ $lesson->subject() }} · {{ $other }}</p>
                    @if ($publishes)
                        <p>{{ __($l.'check.hint') }}</p>
                        <p class="err" id="device-error" hidden>{{ __($l.'check.camera_off') }}</p>
                    @else
                        <p>{{ __($l.'check.observer') }}</p>
                    @endif
                    @if ($recording['mode'] !== 'off' && $recording['missing'] === [] && $recording['controls'] === false)
                        <p class="may-record">{{ __('classroom::recordings.room.may_record') }}</p>
                    @endif
                    <p class="err" id="join-error" role="alert" hidden></p>
                    <div class="row">
                        <button type="button" class="primary" id="join" data-label="{{ __($l.'check.join') }}">{{ __($l.'check.join') }}</button>
                        @if ($publishes) <button type="button" id="join-audio">{{ __($l.'check.join_audio') }}</button> @endif
                    </div>
                </div>
            </section>

            <div class="call" id="call" hidden>
                <div class="banner" id="reconnecting" role="status" hidden>{{ __($r.'reconnecting') }}</div>
                <div class="waiting" id="waiting" hidden>{{ __($r.'waiting', ['name' => $other]) }}</div>
                <div class="tiles" id="tiles" aria-live="polite"></div>
                <p class="err toast" id="rec-error" role="alert" hidden></p>
                <nav class="bar" id="controls">
                    @if ($publishes)
                        <button type="button" class="round toggle mute" id="mic" aria-pressed="true" title="{{ __($r.'mic_off') }} (Ctrl+D)" aria-label="{{ __($r.'mic_off') }}">{!! $icon('mic', 'on') !!}{!! $icon('micOff', 'off') !!}</button>
                        <button type="button" class="round toggle mute" id="camera" aria-pressed="true" title="{{ __($r.'camera_off') }} (Ctrl+E)" aria-label="{{ __($r.'camera_off') }}">{!! $icon('cam', 'on') !!}{!! $icon('camOff', 'off') !!}</button>
                        <button type="button" class="round state wide" id="screen" aria-pressed="false" title="{{ __($r.'screen') }}" aria-label="{{ __($r.'screen') }}">{!! $icon('screen') !!}</button>
                    @endif
                    <button type="button" class="round wide" id="pip" title="{{ __($r.'pip') }}" aria-label="{{ __($r.'pip') }}">{!! $icon('pip') !!}</button>
                    <div class="group wide">
                        <button type="button" class="round" id="layout" aria-expanded="false" aria-controls="layout-menu" title="{{ __($r.'layout') }}" aria-label="{{ __($r.'layout') }}">{!! $icon('grid') !!}</button>
                        <div class="pop" id="layout-menu" hidden>
                            @foreach (['auto', 'grid', 'spotlight'] as $mode)
                                <button type="button" data-layout="{{ $mode }}" aria-pressed="false">{!! $icon($mode) !!}<span>{{ __($r.'layouts.'.$mode) }}</span></button>
                            @endforeach
                        </div>
                    </div>
                    @if ($hasPanel)
                        <button type="button" class="round" id="panel-toggle" aria-expanded="false" aria-controls="panel" title="{{ __($r.'panel') }}" aria-label="{{ __($r.'panel') }}">{!! $icon('panel') !!}</button>
                    @endif
                    <div class="group">
                        <button type="button" class="round" id="more" aria-expanded="false" aria-controls="more-menu" title="{{ __($r.'more') }}" aria-label="{{ __($r.'more') }}">{!! $icon('more') !!}</button>
                        <div class="pop" id="more-menu" hidden>
                            <button type="button" class="narrow" id="pip-more">{!! $icon('pip') !!}<span>{{ __($r.'pip') }}</span></button>
                            @foreach (['auto', 'grid', 'spotlight'] as $mode)
                                <button type="button" class="narrow" data-layout="{{ $mode }}" aria-pressed="false">{!! $icon($mode) !!}<span>{{ __($r.'layout') }}: {{ __($r.'layouts.'.$mode) }}</span></button>
                            @endforeach
                            <div class="sep narrow"></div>
                            <button type="button" id="audio-only" aria-pressed="false">{!! $icon('audio') !!}<span>{{ __($r.'audio_only') }}</span></button>
                            @if ($recording['controls'])
                                <button id="record" class="record" aria-pressed="{{ $recording['running'] ? 'true' : 'false' }}" type="button" @disabled($recording['missing'] !== [] && ! $recording['running'])>
                                    {!! $icon('record') !!}<span class="label">{{ $recording['running'] ? __('classroom::recordings.room.stop') : __('classroom::recordings.room.start') }}</span>
                                </button>
                                @if ($recording['missing'] !== [])
                                    <p class="note" id="rec-missing">{{ __('classroom::recordings.errors.consent_missing', ['names' => implode('، ', $recording['missing'])]) }}</p>
                                @endif
                            @endif
                        </div>
                    </div>
                    <button type="button" class="leave" id="leave" title="{{ __($r.'leave_hint') }}" aria-label="{{ __($r.'leave_hint') }}">{!! $icon('leave') !!}<span class="label">{{ __($r.'leave') }}</span></button>
                </nav>
            </div>

            @if ($mushaf)
                {{-- A lesson on Zoom or Meet opens in a tab of its own, so the mushaf stays open here beside it. --}}
                <section class="notice" id="outside" hidden>
                    <h1>{{ __($l.'external.opened') }}</h1>
                    <div class="row"><a class="button primary" href="{{ $goUrl }}" target="_blank" rel="noopener">{{ __($l.'external.again') }}</a></div>
                </section>
            @endif

            <section class="notice" id="left" hidden>
                <h1>{{ __($r.'left') }}</h1>
                <div class="row"><button type="button" class="primary" id="rejoin">{{ __($r.'rejoin') }}</button></div>
            </section>
        </div>

        @if ($hasPanel)
            <aside id="panel" aria-label="{{ __($r.'panel') }}">
                <div class="panel-head">
                    <strong>{{ __($r.'panel') }}</strong>
                    <button type="button" class="round" id="panel-close" title="{{ __($r.'panel_close') }}" aria-label="{{ __($r.'panel_close') }}">{!! $icon('close') !!}</button>
                </div>
                @if ($mushaf) @include('classroom::mushaf') @endif
            @if ($seat->role !== 'student')
                <h2>{{ __($l.'plan.title') }}</h2>
                @forelse ($plan as $line)
                    <div class="line">
                        <span class="tag">{{ __($l.'plan.'.$line->reason) }}</span>
                        <strong>{{ $line->title }}</strong>
                        <small>{{ $line->path }}</small>
                        @if ($line->objectives) <p>{{ $line->objectives }}</p> @endif
                        @include('classroom::materials', ['items' => $line->materials, 'icons' => $materialIcons])
                    </div>
                @empty
                    <small class="none">{{ __($l.'plan.none') }}</small>
                @endforelse

                <h2>{{ __($l.'homework.title') }}</h2>
                @if ($homework)
                    <div class="line homework {{ $homework->status }}">
                        <span class="tag">{{ __($l.'homework.statuses.'.$homework->status, ['grade' => $homework->grade ?? '–']) }}</span>
                        <strong>{{ $homework->title }}</strong>
                        <small>{{ __($l.'homework.due', ['date' => $homework->dueAt->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F')]) }}</small>
                        @if ($homework->instructions) <p>{{ \Illuminate\Support\Str::limit($homework->instructions, 220) }}</p> @endif
                        @if ($homework->feedback) <p>“{{ \Illuminate\Support\Str::limit($homework->feedback, 160) }}”</p> @endif
                    </div>
                @else
                    <small class="none">{{ __($l.'homework.none') }}</small>
                @endif
            @elseif ($materials->isNotEmpty())
                <h2>{{ __($l.'materials.title') }}</h2>
                <small class="none">{{ __($l.'materials.hint') }}</small>
                @include('classroom::materials', ['items' => $materials->all(), 'icons' => $materialIcons])
            @endif
            </aside>
        @endif
    </main>

    <script>
    (() => {
        const cfg = {{ Illuminate\Support\Js::from($cfg) }};
        const $ = (id) => document.getElementById(id);
        const LK = window.LivekitClient;
        const W = cfg.words;
        const publishes = cfg.role !== 'observer';
        const rtl = document.documentElement.dir === 'rtl';
        const motion = window.matchMedia('(prefers-reduced-motion: no-preference)');
        const csrf = () => document.querySelector('meta[name=csrf-token]').content;
        const store = { get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };
        const svg = (name, cls = '') => `<svg class="ic ${cls}" viewBox="0 0 24 24" aria-hidden="true"><path d="${cfg.icons[name]}"/></svg>`;
        const label = (el, text) => { if (el) { el.title = text; el.setAttribute('aria-label', text); } };
        const hue = (s) => { let h = 0; for (const c of String(s)) h = (h * 31 + c.codePointAt(0)) % 360; return h; };

        let room = null, preview = null, audioOnly = false, meterTimer = null, audioCtx = null;
        let preMic = true, preCam = true;
        let choice = ['auto', 'grid', 'spotlight'].includes(store.get('lms.room.layout')) ? store.get('lms.room.layout') : 'auto';
        let corner = /^[tb][se]$/.test(store.get('lms.room.corner') || '') ? store.get('lms.room.corner') : 'be';
        let pinned = null, speaker = null, mainKey = null, dragging = false;
        let startedAt = 0, clock = null, pipWin = null, pipTrack = null;
        const tiles = new Map();   // "identity:camera|screen" → tile

        // ——— States: prejoin, joining, live, left, outside ———
        const setState = (s) => {
            document.body.dataset.state = s;
            $('prejoin').hidden = s !== 'prejoin' && s !== 'joining';
            $('call').hidden = s !== 'live';
            $('left').hidden = s !== 'left';
            if ($('outside')) $('outside').hidden = s !== 'outside';
            $('timer').hidden = $('people').hidden = $('quality').hidden = s !== 'live';
            if (s !== 'live') { $('waiting').hidden = true; $('reconnecting').hidden = true; stopClock(); closePip(); closePops(); }
            else layout(false);
        };

        // ——— Before joining: the camera and microphone check ———
        async function startPreview() {
            if (!publishes) return;
            try { preview = await navigator.mediaDevices.getUserMedia({ video: true, audio: true }); }
            catch (e) {
                $('device-error').hidden = false;
                try { preview = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch (e2) { preview = null; }
            }
            if (!preview) { applyPre(); return; }
            $('preview').srcObject = preview;
            applyPre();
            try {
                audioCtx = new AudioContext();
                const an = audioCtx.createAnalyser();
                audioCtx.createMediaStreamSource(preview).connect(an);
                const buf = new Uint8Array(an.fftSize);
                meterTimer = setInterval(() => {
                    an.getByteTimeDomainData(buf); let m = 0; for (const v of buf) m = Math.max(m, Math.abs(v - 128));
                    $('meter').style.transform = `scaleX(${preMic ? Math.min(1, m / 60) : 0})`;
                }, 100);
            } catch (e) {}
        }
        function stopPreview() {
            clearInterval(meterTimer); meterTimer = null;
            try { audioCtx?.close(); } catch (e) {}
            audioCtx = null;
            preview?.getTracks().forEach((t) => t.stop()); preview = null;
        }
        function applyPre() {
            if (!publishes) return;
            preview?.getAudioTracks().forEach((t) => { t.enabled = preMic; });
            preview?.getVideoTracks().forEach((t) => { t.enabled = preCam; });
            $('pre-mic').setAttribute('aria-pressed', String(preMic));
            $('pre-cam').setAttribute('aria-pressed', String(preCam));
            label($('pre-mic'), preMic ? W.micOff : W.micOn);
            label($('pre-cam'), preCam ? W.camOff : W.camOn);
            $('preview-off').hidden = preCam && !!preview?.getVideoTracks().length;
        }

        // ——— Tiles ———
        function tileFor(participant, source = 'camera') {
            const key = participant.identity + ':' + source;
            let tile = tiles.get(key);
            if (tile) return tile;
            const local = participant === room.localParticipant;
            const who = local ? W.you : (participant.name || participant.identity);
            const name = source === 'screen' ? W.screenOf.replace(':name', who) : who;
            tile = document.createElement('div');
            tile.className = 'tile' + (local ? ' local' : '') + (source === 'screen' ? ' screen' : '');
            tile.dataset.key = key; tile.dataset.identity = participant.identity;
            tile.tabIndex = 0; tile.setAttribute('role', 'button'); tile.setAttribute('aria-pressed', 'false');
            tile.innerHTML = '<div class="avatar"><span></span></div><video autoplay playsinline muted></video>'
                + `<span class="name">${svg('micOff', 'mute-ic')}<span></span></span><span class="tq"><i></i><i></i><i></i></span><span class="pin-ic">${svg('pin')}</span>`
                + (local && source === 'screen' ? '<span class="presenting"></span>' : '');
            tile.querySelector('.avatar span').textContent = (name || '?').trim().charAt(0);
            tile.querySelector('.avatar span').style.setProperty('--hue', hue(participant.identity));
            tile.querySelector('.name > span').textContent = name;
            if (tile.querySelector('.presenting')) tile.querySelector('.presenting').textContent = W.presenting;
            tile.title = local && source === 'camera' ? W.selfView : W.pin;
            tile.setAttribute('aria-label', name);
            tile.addEventListener('click', () => { if (tile.dataset.dragged) { delete tile.dataset.dragged; return; } pin(key); });
            tile.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pin(key); } });
            if (local && source === 'camera') draggable(tile);
            tiles.set(key, tile);
            $('tiles').appendChild(tile);
            if (source === 'camera') syncMic(participant);
            layout(true);
            return tile;
        }
        function removeTile(key) {
            const tile = tiles.get(key);
            if (!tile) return;
            tile.remove(); tiles.delete(key);
            if (pinned === key) pinned = null;
            if (speaker === key) speaker = null;
            layout(true);
        }
        const sourceOf = (track) => track.source === LK.Track.Source.ScreenShare ? 'screen' : 'camera';
        function attach(track, participant) {
            if (track.kind === 'audio') { if (participant !== room.localParticipant) document.body.appendChild(track.attach()); return; }
            const tile = tileFor(participant, sourceOf(track));
            track.attach(tile.querySelector('video')); tile._track = track; tile.classList.add('has-video');
            layout(true);
        }
        function detach(track, participant) {
            track.detach().forEach((el) => { if (el.tagName === 'AUDIO') el.remove(); });
            if (track.kind === 'audio') return;
            const key = participant.identity + ':' + sourceOf(track);
            const tile = tiles.get(key);
            if (!tile) return;
            tile.classList.remove('has-video'); tile._track = null;
            if (track.source === LK.Track.Source.ScreenShare) removeTile(key); else renderPip();
        }
        function micOn(p) { const pub = p.getTrackPublication(LK.Track.Source.Microphone); return !!pub && !pub.isMuted; }
        function syncMic(p) {
            const tile = tiles.get(p.identity + ':camera');
            if (tile) { tile.classList.toggle('muted', !micOn(p)); tile.querySelector('.mute-ic').setAttribute('aria-label', W.muted); }
        }
        function videoShown(pub, p) {
            const tile = tiles.get(p.identity + ':' + (pub.source === LK.Track.Source.ScreenShare ? 'screen' : 'camera'));
            if (tile) { tile.classList.toggle('has-video', !pub.isMuted && !!pub.track); renderPip(); }
        }
        function pin(key) {
            pinned = pinned === key ? null : key;
            tiles.forEach((t, k) => { t.classList.toggle('pinned', k === pinned); t.setAttribute('aria-pressed', String(k === pinned)); });
            layout(true);
        }
        function refreshPeople() {
            if (!room) return;
            $('waiting').hidden = room.remoteParticipants.size > 0;
            $('people-n').textContent = String(room.remoteParticipants.size + (publishes ? 1 : 0));
        }

        // ——— Layouts: auto (one other: they fill the stage, you float in a corner), grid, spotlight (a shared screen, a pin) ———
        function fit(n, w, h, gap) {
            let best = { cols: 1, rows: n, tw: 0 };
            for (let cols = 1; cols <= n; cols++) {
                const rows = Math.ceil(n / cols);
                const tw = Math.min((w - gap * (cols - 1)) / cols, ((h - gap * (rows - 1)) / rows) * 16 / 9);
                if (tw > best.tw) best = { cols, rows, tw };
            }
            return best;
        }
        function plan(list) {
            const screens = list.filter((t) => t.classList.contains('screen'));
            const self = list.find((t) => t.classList.contains('local') && !t.classList.contains('screen'));
            if (pinned && tiles.has(pinned) && list.length > 1) return { mode: 'spotlight', main: tiles.get(pinned) };
            if (screens.length && choice !== 'grid') return { mode: 'spotlight', main: screens.find((t) => !t.classList.contains('local')) || screens[0] };
            if (choice === 'spotlight' && list.length > 1) {
                const main = (speaker && tiles.get(speaker)) || list.find((t) => !t.classList.contains('local')) || list[0];
                return { mode: 'spotlight', main };
            }
            if (choice === 'auto' && list.length === 2 && self) return { mode: 'float', main: list.find((t) => t !== self), self };
            return { mode: 'grid', main: list.length === 1 ? list[0] : null };
        }
        function layout(animate) {
            const box = $('tiles');
            if (dragging || $('call').hidden) return;
            const Wd = box.clientWidth, H = box.clientHeight;
            const list = [...tiles.values()];
            if (!Wd || !H || !list.length) { mainKey = null; renderPip(); return; }
            const gap = Wd < 600 ? 8 : 12;
            const { mode, main, self } = plan(list);
            mainKey = main ? main.dataset.key : null;
            const spots = new Map();   // tile → [x from the start edge, y, w, h]
            if (mode === 'float') {
                spots.set(main, [0, 0, Wd, H]);
                const portrait = H > Wd;
                const w = portrait ? Math.max(84, Math.min(140, Wd * 0.3)) : Math.max(140, Math.min(280, Wd * 0.2));
                const h = portrait ? w * 4 / 3 : w * 9 / 16, m = 12;
                spots.set(self, [corner[1] === 's' ? m : Wd - m - w, corner[0] === 't' ? m : H - m - h, w, h]);
            } else if (mode === 'spotlight') {
                const rest = list.filter((t) => t !== main);
                if (!rest.length) spots.set(main, [0, 0, Wd, H]);
                else if (Wd >= 720 && Wd / H >= 1.2) {
                    const sw = Math.max(150, Math.min(260, Wd * 0.2));
                    let tw = sw, th = tw * 9 / 16;
                    if (rest.length * th + (rest.length - 1) * gap > H) { th = (H - (rest.length - 1) * gap) / rest.length; tw = th * 16 / 9; }
                    spots.set(main, [0, 0, Wd - sw - gap, H]);
                    let y = (H - (rest.length * th + (rest.length - 1) * gap)) / 2;
                    rest.forEach((t) => { spots.set(t, [Wd - sw + (sw - tw) / 2, y, tw, th]); y += th + gap; });
                } else {
                    const sh = Math.max(64, Math.min(120, H * 0.2));
                    let th = sh, tw = th * 16 / 9;
                    if (rest.length * tw + (rest.length - 1) * gap > Wd) { tw = (Wd - (rest.length - 1) * gap) / rest.length; th = tw * 9 / 16; }
                    spots.set(main, [0, 0, Wd, H - sh - gap]);
                    let x = (Wd - (rest.length * tw + (rest.length - 1) * gap)) / 2;
                    rest.forEach((t) => { spots.set(t, [x, H - sh + (sh - th) / 2, tw, th]); x += tw + gap; });
                }
            } else {
                const { cols, rows, tw } = fit(list.length, Wd, H, gap), th = tw * 9 / 16;
                let y = (H - (rows * th + (rows - 1) * gap)) / 2;
                for (let r = 0; r < rows; r++) {
                    const row = list.slice(r * cols, r * cols + cols);
                    let x = (Wd - (row.length * tw + (row.length - 1) * gap)) / 2;
                    row.forEach((t) => { spots.set(t, [x, y, tw, th]); x += tw + gap; });
                    y += th + gap;
                }
            }
            spots.forEach(([x, y, w, h], t) => {
                t.classList.toggle('main', t === main && mode !== 'grid');
                t.classList.toggle('strip', mode === 'spotlight' && t !== main);
                t.classList.toggle('float', mode === 'float' && t === self);
                place(t, rtl ? Wd - x - w : x, y, w, h, animate);
            });
            renderPip();
        }
        function place(t, x, y, w, h, animate) {
            const before = animate && t.dataset.placed && motion.matches ? t.getBoundingClientRect() : null;
            t.style.transform = '';
            Object.assign(t.style, { left: x + 'px', top: y + 'px', width: w + 'px', height: h + 'px' });
            t.dataset.placed = '1';
            if (!before) return;
            const now = t.getBoundingClientRect();
            if (!now.width || (Math.abs(before.left - now.left) < 1 && Math.abs(before.top - now.top) < 1 && Math.abs(before.width - now.width) < 1)) return;
            t.classList.remove('moving');
            t.style.transformOrigin = '0 0';
            t.style.transform = `translate(${before.left - now.left}px, ${before.top - now.top}px) scale(${before.width / now.width}, ${before.height / now.height})`;
            void t.offsetWidth;
            t.classList.add('moving'); t.style.transform = '';
            clearTimeout(t._moving); t._moving = setTimeout(() => t.classList.remove('moving'), 340);
        }
        // Your own picture, while it floats: drag it, and it settles in the nearest corner.
        function draggable(tile) {
            let start = null;
            tile.addEventListener('pointerdown', (e) => {
                if (!tile.classList.contains('float') || e.button > 0) return;
                start = { x: e.clientX, y: e.clientY, moved: false };
                try { tile.setPointerCapture(e.pointerId); } catch (err) {}
            });
            tile.addEventListener('pointermove', (e) => {
                if (!start) return;
                const dx = e.clientX - start.x, dy = e.clientY - start.y;
                if (!start.moved && Math.hypot(dx, dy) < 5) return;
                start.moved = true; dragging = true;
                tile.classList.add('dragging'); tile.classList.remove('moving');
                tile.style.transform = `translate(${dx}px, ${dy}px)`;
            });
            const end = () => {
                if (!start) return;
                const moved = start.moved; start = null; dragging = false;
                tile.classList.remove('dragging');
                if (!moved) return;
                tile.dataset.dragged = '1';
                setTimeout(() => delete tile.dataset.dragged, 0);
                const box = $('tiles').getBoundingClientRect(), r = tile.getBoundingClientRect();
                const right = r.left + r.width / 2 - box.left > box.width / 2, bottom = r.top + r.height / 2 - box.top > box.height / 2;
                corner = (bottom ? 'b' : 't') + ((rtl ? !right : right) ? 'e' : 's');
                store.set('lms.room.corner', corner);
                layout(true);
            };
            tile.addEventListener('pointerup', end);
            tile.addEventListener('pointercancel', end);
        }
        new ResizeObserver(() => layout(false)).observe($('tiles'));
        function choose(mode) {
            choice = mode; store.set('lms.room.layout', mode);
            if (mode === 'grid') pinned = null;
            tiles.forEach((t, k) => { t.classList.toggle('pinned', k === pinned); t.setAttribute('aria-pressed', String(k === pinned)); });
            document.querySelectorAll('[data-layout]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.layout === choice)));
            layout(true);
        }
        document.querySelectorAll('[data-layout]').forEach((b) => b.addEventListener('click', () => { choose(b.dataset.layout); closePops(); }));

        // ——— The bar ———
        function syncButtons() {
            if (!publishes || !room) return;
            const lp = room.localParticipant;
            $('mic').setAttribute('aria-pressed', String(lp.isMicrophoneEnabled));
            $('camera').setAttribute('aria-pressed', String(lp.isCameraEnabled));
            $('screen').setAttribute('aria-pressed', String(lp.isScreenShareEnabled));
            label($('mic'), lp.isMicrophoneEnabled ? W.micOff : W.micOn);
            label($('camera'), lp.isCameraEnabled ? W.camOff : W.camOn);
            label($('screen'), lp.isScreenShareEnabled ? W.screenStop : W.screen);
            const pipMic = pipWin?.document.getElementById('pip-mic');
            if (pipMic) { pipMic.setAttribute('aria-pressed', String(lp.isMicrophoneEnabled)); label(pipMic, lp.isMicrophoneEnabled ? W.micOff : W.micOn); }
            syncMic(lp);
        }
        async function setAudioOnly(on) {
            audioOnly = on; $('audio-only').setAttribute('aria-pressed', String(on));
            if (publishes && on) await room.localParticipant.setCameraEnabled(false);
            for (const p of room.remoteParticipants.values()) for (const pub of p.videoTrackPublications.values()) pub.setSubscribed(!on);
            syncButtons();
        }
        async function toggleMic() { if (!room || !publishes) return; try { await room.localParticipant.setMicrophoneEnabled(!room.localParticipant.isMicrophoneEnabled); } catch (e) {} syncButtons(); }
        async function toggleCamera() { if (!room || !publishes) return; if (audioOnly) await setAudioOnly(false); try { await room.localParticipant.setCameraEnabled(!room.localParticipant.isCameraEnabled); } catch (e) {} syncButtons(); }
        function closePops(except) {
            [['layout', 'layout-menu'], ['more', 'more-menu']].forEach(([b, m]) => { if (m !== except) { $(m).hidden = true; $(b).setAttribute('aria-expanded', 'false'); } });
        }
        [['layout', 'layout-menu'], ['more', 'more-menu']].forEach(([b, m]) => $(b).addEventListener('click', (e) => {
            e.stopPropagation(); closePops(m);
            const open = $(m).hidden; $(m).hidden = !open; $(b).setAttribute('aria-expanded', String(open));
            if (open) $(m).querySelector('button:not([disabled])')?.focus();
        }));
        document.addEventListener('click', (e) => { if (!e.target.closest('.pop')) closePops(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePops();
            if (!(e.ctrlKey || e.metaKey) || e.altKey || e.shiftKey || !publishes) return;
            const k = e.code === 'KeyD' ? 'mic' : e.code === 'KeyE' ? 'cam' : null;
            if (!k) return;
            const s = document.body.dataset.state;
            if (s === 'live') { e.preventDefault(); k === 'mic' ? toggleMic() : toggleCamera(); }
            else if (s === 'prejoin') { e.preventDefault(); if (k === 'mic') preMic = !preMic; else preCam = !preCam; applyPre(); }
        });

        // The side panel: open by default on a wide screen when it holds the mushaf.
        const wide = window.matchMedia('(min-width: 1000px)');
        function panel(open) {
            document.body.dataset.panel = open ? 'open' : 'closed';
            $('panel-toggle')?.setAttribute('aria-expanded', String(open));
            $('panel-toggle')?.setAttribute('aria-pressed', String(open));
            requestAnimationFrame(() => layout(false));
        }
        panel(!!$('panel') && cfg.mushaf && wide.matches);
        $('panel-toggle')?.addEventListener('click', () => panel(document.body.dataset.panel !== 'open'));
        $('panel-close')?.addEventListener('click', () => { panel(false); $('panel-toggle')?.focus(); });

        // The timer: since you joined.
        function startClock() {
            startedAt = Date.now(); stopClock();
            const tick = () => {
                const s = Math.floor((Date.now() - startedAt) / 1000), p = (n) => String(n).padStart(2, '0');
                $('timer').textContent = (s >= 3600 ? Math.floor(s / 3600) + ':' + p(Math.floor(s / 60) % 60) : p(Math.floor(s / 60))) + ':' + p(s % 60);
            };
            tick(); clock = setInterval(tick, 1000);
        }
        function stopClock() { clearInterval(clock); clock = null; }

        // ——— Picture in picture: the main video and the mic and leave buttons in a small window of their own ———
        const docPip = 'documentPictureInPicture' in window;
        const videoPip = !!document.pictureInPictureEnabled;
        if (!docPip && !videoPip) { $('pip').hidden = true; $('pip-more').hidden = true; }
        function mainTile() {
            const all = [...tiles.values()];
            return (mainKey && tiles.get(mainKey)) || all.find((t) => !t.classList.contains('local')) || all[0] || null;
        }
        async function enterPip(auto) {
            if (document.body.dataset.state !== 'live' || pipWin) return;
            if (docPip) {
                try { pipWin = await window.documentPictureInPicture.requestWindow({ width: 360, height: 220 }); }
                catch (e) { pipWin = null; if (!auto) await videoInPip(); return; }
                buildPip();
                return;
            }
            await videoInPip();
        }
        async function videoInPip() {
            const t = mainTile(), v = t?.querySelector('video');
            if (!v || !t.classList.contains('has-video') || document.pictureInPictureElement === v) return;
            try { await v.requestPictureInPicture(); } catch (e) {}
        }
        function buildPip() {
            const d = pipWin.document;
            d.documentElement.lang = document.documentElement.lang; d.documentElement.dir = document.documentElement.dir;
            d.title = document.title;
            const style = d.createElement('style'); style.textContent = $('room-styles').textContent; d.head.append(style);
            d.body.className = 'pip';
            d.body.innerHTML = '<div class="pip-stage" id="pip-stage"><div class="avatar"><span></span></div><video autoplay playsinline muted></video><span class="name"></span></div>'
                + '<div class="pip-bar">'
                + (publishes ? `<button type="button" class="round toggle mute" id="pip-mic" aria-pressed="true">${svg('mic', 'on')}${svg('micOff', 'off')}</button>` : '')
                + `<button type="button" class="leave" id="pip-leave">${svg('leave')}</button></div>`;
            label(d.getElementById('pip-leave'), W.leave);
            d.getElementById('pip-mic')?.addEventListener('click', toggleMic);
            d.getElementById('pip-leave').addEventListener('click', () => room?.disconnect());
            pipWin.addEventListener('pagehide', () => {
                const v = d.querySelector('video');
                try { pipTrack?.detach(v); } catch (e) {}
                pipWin = null; pipTrack = null;
            });
            pipTrack = null;
            renderPip(); syncButtons();
        }
        function renderPip() {
            if (!pipWin) return;
            const d = pipWin.document, stage = d.getElementById('pip-stage');
            if (!stage) return;
            const t = mainTile(), v = stage.querySelector('video');
            const track = t?._track && t.classList.contains('has-video') ? t._track : null;
            if (track !== pipTrack) {
                try { pipTrack?.detach(v); } catch (e) {}
                pipTrack = track;
                if (track) track.attach(v);
            }
            stage.classList.toggle('has-video', !!track);
            stage.classList.toggle('local', !!t?.classList.contains('local'));
            stage.classList.toggle('screen', !!t?.classList.contains('screen'));
            const a = stage.querySelector('.avatar span');
            a.textContent = t?.querySelector('.avatar span')?.textContent || '';
            a.style.setProperty('--hue', t?.querySelector('.avatar span')?.style.getPropertyValue('--hue') || '200');
            stage.querySelector('.name').textContent = t?.querySelector('.name > span')?.textContent || '';
        }
        function closePip() {
            try { pipWin?.close(); } catch (e) {}
            if (document.pictureInPictureElement) document.exitPictureInPicture().catch(() => {});
        }
        $('pip').addEventListener('click', () => enterPip(false));
        $('pip-more').addEventListener('click', () => { closePops(); enterPip(false); });
        // Leaving the tab during the lesson: the browser may open it by itself (Chrome's automatic picture in picture for calls).
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden' && document.body.dataset.state === 'live') enterPip(true).catch(() => {}); });
        function mediaSession(on) {
            try { navigator.mediaSession?.setActionHandler('enterpictureinpicture', on ? () => { enterPip(false).catch(() => {}); } : null); } catch (e) {}
        }

        // ——— Joining ———
        async function join(mode) {
            const withVideo = mode !== 'audio';
            $('join-error').hidden = true; $('join').disabled = true; $('join').textContent = cfg.joining; setState('joining');
            let ticket;
            try {
                const res = await fetch(cfg.ticketUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() } });
                const body = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(body?.errors?.lesson?.[0] || body?.message || cfg.providerError);
                ticket = body.data;
            } catch (e) { return fail(e.message); }

            if (ticket.provider === 'external') {
                // Beside a Quran lesson's mushaf, Zoom or Meet opens in a tab of its own and this page stays.
                if (!cfg.mushaf) { window.location.href = cfg.goUrl; return; }
                stopPreview(); setState('outside'); $('join').disabled = false; $('join').textContent = $('join').dataset.label;
                window.open(cfg.goUrl, '_blank', 'noopener');
                return;
            }
            if (!LK) return fail(cfg.providerError);

            stopPreview();
            room = new LK.Room({ adaptiveStream: true, dynacast: true });
            room.on(LK.RoomEvent.TrackSubscribed, (t, pub, p) => { attach(t, p); if (audioOnly && t.kind === 'video') pub.setSubscribed(false); })
                .on(LK.RoomEvent.TrackUnsubscribed, (t, pub, p) => detach(t, p))
                .on(LK.RoomEvent.LocalTrackPublished, (pub, p) => { if (pub.track) attach(pub.track, p); syncButtons(); })
                .on(LK.RoomEvent.LocalTrackUnpublished, (pub, p) => { if (pub.track) detach(pub.track, p); syncButtons(); })
                .on(LK.RoomEvent.TrackPublished, (pub, p) => syncMic(p))
                .on(LK.RoomEvent.TrackUnpublished, (pub, p) => syncMic(p))
                .on(LK.RoomEvent.TrackMuted, (pub, p) => { if (pub.kind === 'video') videoShown(pub, p); else syncMic(p); syncButtons(); })
                .on(LK.RoomEvent.TrackUnmuted, (pub, p) => { if (pub.kind === 'video') videoShown(pub, p); else syncMic(p); syncButtons(); })
                .on(LK.RoomEvent.ParticipantConnected, (p) => { tileFor(p); refreshPeople(); })
                .on(LK.RoomEvent.ParticipantDisconnected, (p) => { [...tiles.keys()].filter((k) => tiles.get(k).dataset.identity === p.identity).forEach(removeTile); refreshPeople(); })
                .on(LK.RoomEvent.ActiveSpeakersChanged, (speakers) => {
                    tiles.forEach((t) => t.classList.toggle('speaking', !t.classList.contains('screen') && speakers.some((s) => s.identity === t.dataset.identity)));
                    const loud = speakers.find((s) => s !== room.localParticipant);
                    if (loud && speaker !== loud.identity + ':camera') { speaker = loud.identity + ':camera'; if (choice === 'spotlight' && !pinned) layout(true); }
                })
                .on(LK.RoomEvent.ConnectionQualityChanged, (q, p) => {
                    tiles.forEach((t) => { if (t.dataset.identity === p.identity) t.dataset.q = q; });
                    if (p === room.localParticipant) { $('quality').dataset.q = q; $('quality').title = cfg.quality[q] ?? ''; $('quality').querySelector('.q-text').textContent = cfg.quality[q] ?? ''; }
                })
                .on(LK.RoomEvent.Reconnecting, () => { $('reconnecting').hidden = false; })
                .on(LK.RoomEvent.Reconnected, () => { $('reconnecting').hidden = true; })
                .on(LK.RoomEvent.Disconnected, () => {
                    mediaSession(false);
                    tiles.forEach((t) => t.remove()); tiles.clear(); pinned = speaker = mainKey = null;
                    setState('left');
                    document.dispatchEvent(new CustomEvent('lms:room', { detail: null }));
                })
                .on(LK.RoomEvent.RecordingStatusChanged, () => showRecording())
                .on(LK.RoomEvent.RoomMetadataChanged, () => { recording = !!roomMeta().recording; showRecording(); });
            try {
                await room.connect(ticket.url, ticket.token);
            } catch (e) { return fail(cfg.providerError); }
            // The mushaf beside the room hears the teacher's turns through it (lms:room).
            document.dispatchEvent(new CustomEvent('lms:room', { detail: room }));
            setState('live');
            startClock();
            if (publishes) tileFor(room.localParticipant);
            room.remoteParticipants.forEach((p) => { tileFor(p); syncMic(p); });
            refreshPeople();
            if (publishes) {
                if (preMic) { try { await room.localParticipant.setMicrophoneEnabled(true); } catch (e) {} }
                // The academy may have a student come in with the camera off (under-18s, or everyone): they can switch it on.
                if (withVideo && preCam && ticket.camera !== false) { try { await room.localParticipant.setCameraEnabled(true); } catch (e) {} }
            }
            if (!withVideo) await setAudioOnly(true);
            syncButtons();
            if ('recording' in roomMeta()) recording = !!roomMeta().recording;
            showRecording();
            mediaSession(true);
        }
        // Everyone sees when the lesson is being recorded: LiveKit says so itself, and the room's metadata too.
        const rec = cfg.recording;
        let recording = rec.running;
        function roomMeta() { try { return JSON.parse(room?.metadata || '{}') || {}; } catch (e) { return {}; } }
        function showRecording() {
            const on = recording || !!room?.isRecording;
            $('rec').hidden = !on;
            const btn = $('record');
            if (btn) { btn.setAttribute('aria-pressed', on ? 'true' : 'false'); btn.querySelector('.label').textContent = on ? rec.labels.stop : rec.labels.start; if (on) btn.disabled = false; }
        }
        $('record')?.addEventListener('click', async () => {
            const btn = $('record'), stop = btn.getAttribute('aria-pressed') === 'true';
            btn.disabled = true; $('rec-error').hidden = true;
            try {
                const res = await fetch(stop ? rec.stopUrl : rec.startUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() } });
                const body = await res.json().catch(() => ({}));
                if (!res.ok) { $('rec-error').textContent = body.message || cfg.providerError; $('rec-error').hidden = false; }
                else { recording = !!body.data?.running; }
            } catch (e) { $('rec-error').textContent = cfg.providerError; $('rec-error').hidden = false; }
            btn.disabled = false; showRecording();
        });
        function fail(message) {
            setState('prejoin'); $('join').disabled = false; $('join').textContent = $('join').dataset.label;
            $('join-error').textContent = message; $('join-error').hidden = false; startPreview();
        }

        $('join').addEventListener('click', () => join('av'));
        $('join-audio')?.addEventListener('click', () => join('audio'));
        $('rejoin').addEventListener('click', () => { setState('prejoin'); join(audioOnly ? 'audio' : 'av'); });
        $('leave').addEventListener('click', () => room?.disconnect());
        $('audio-only').addEventListener('click', () => setAudioOnly(!audioOnly));
        if (publishes) {
            $('pre-mic').addEventListener('click', () => { preMic = !preMic; applyPre(); });
            $('pre-cam').addEventListener('click', () => { preCam = !preCam; applyPre(); });
            $('mic').addEventListener('click', toggleMic);
            $('camera').addEventListener('click', toggleCamera);
            $('screen').addEventListener('click', async () => { try { await room.localParticipant.setScreenShareEnabled(!room.localParticipant.isScreenShareEnabled); } catch (e) {} syncButtons(); });
            if (!navigator.mediaDevices?.getDisplayMedia) $('screen').hidden = true;
        }
        choose(choice);
        window.addEventListener('beforeunload', () => { closePip(); room?.disconnect(); });
        startPreview();
    })();
    </script>
</body>
</html>
