@php
    $l = 'classroom::classroom.';
    $other = $seat->role === 'teacher' ? $lesson->studentName : $lesson->teacherName;
    $rtl = app()->getLocale() === 'ar';
    $cfg = [
        'ticketUrl' => $ticketUrl,
        'goUrl' => $goUrl,
        'role' => $seat->role,
        'identity' => $seat->identity,
        'you' => __($l.'room.you'),
        'joining' => __($l.'check.joining'),
        'quality' => trans($l.'room.quality'),
        'providerError' => __($l.'errors.provider'),
        'mushaf' => $mushaf !== null,
        'recording' => $recording + [
            'labels' => ['start' => __('classroom::recordings.room.start'), 'stop' => __('classroom::recordings.room.stop')],
        ],
    ];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __($l.'title') }} · {{ $lesson->subject() }} · {{ $brand->name() }}</title>
    @if ($brand->faviconUrl) <link rel="icon" href="{{ $brand->faviconUrl }}"> @endif
    <style>
        :root {
            --brand: {{ $brand->tokens['primary'] ?? '#0F766E' }};
            --danger: {{ $brand->tokens['danger'] ?? '#DC2626' }};
            --bg: #0f1115; --panel: #181b21; --line: #2a2f38; --text: #f4f4f5; --muted: #a1a1aa;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body { background: var(--bg); color: var(--text); font-family: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, Arial, sans-serif; display: flex; flex-direction: column; }
        header { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--line); background: var(--panel); }
        header .brand { font-weight: 700; white-space: nowrap; }
        header .lesson { display: flex; flex-direction: column; min-width: 0; flex: 1; }
        header .lesson strong { font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        header .lesson small { color: var(--muted); font-size: 12px; }
        header .concern { color: var(--muted); font-size: 12px; text-decoration: underline; white-space: nowrap; }
        header .concern:hover { color: var(--text); }
        .chip { font-size: 12px; padding: 2px 10px; border-radius: 999px; background: color-mix(in oklab, var(--brand) 25%, transparent); color: #fff; white-space: nowrap; }
        .quality { font-size: 12px; color: var(--muted); white-space: nowrap; }
        .quality[data-q="poor"], .quality[data-q="lost"] { color: #fbbf24; }
        main { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; padding: 12px 16px; min-height: 0; }
        main.with-plan { grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 1000px) { main.with-plan { grid-template-columns: minmax(0, 1fr) 300px; } main.with-mushaf { grid-template-columns: minmax(0, 1fr) 440px; } }
        @media (max-width: 999px) { main.with-mushaf { overflow-y: auto; } main.with-mushaf aside { overflow: visible; } }
        aside .mushaf:not(:last-child) { padding-bottom: 12px; border-bottom: 1px solid var(--line); }
        a.button { display: inline-block; text-decoration: none; border-radius: 10px; padding: 10px 16px; background: var(--line); color: var(--text); }
        a.button.primary { background: var(--brand); color: #fff; font-weight: 700; }
        .stage { position: relative; display: flex; flex-direction: column; gap: 12px; min-height: 0; }
        .prejoin, .notice { margin: auto; width: 100%; max-width: 520px; background: var(--panel); border: 1px solid var(--line); border-radius: 16px; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
        .prejoin h1, .notice h1 { margin: 0; font-size: 18px; }
        .prejoin p, .notice p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.6; }
        .preview { aspect-ratio: 16 / 9; background: #000; border-radius: 12px; overflow: hidden; position: relative; }
        .preview video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        .meter { height: 6px; border-radius: 999px; background: var(--line); overflow: hidden; }
        .meter i { display: block; height: 100%; width: 0; background: var(--brand); transition: width .1s; }
        .row { display: flex; gap: 8px; flex-wrap: wrap; }
        button { font: inherit; border: 0; border-radius: 10px; padding: 10px 16px; cursor: pointer; background: var(--line); color: var(--text); }
        button.primary { background: var(--brand); color: #fff; font-weight: 700; }
        button.danger { background: var(--danger); color: #fff; }
        button.mute[aria-pressed="false"] { background: #3f1d1d; color: #fecaca; }   /* mic or camera off */
        button.toggle[aria-pressed="true"] { background: color-mix(in oklab, var(--brand) 70%, #000); color: #fff; }   /* sharing, audio only */
        button:disabled { opacity: .6; cursor: default; }
        .err { color: #fca5a5; font-size: 14px; }
        .tiles { flex: 1; display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); align-content: center; min-height: 0; }
        .tile { position: relative; aspect-ratio: 16 / 9; background: #000; border-radius: 14px; overflow: hidden; border: 2px solid transparent; }
        .tile.speaking { border-color: var(--brand); }
        .tile video { width: 100%; height: 100%; object-fit: cover; }
        .tile.local video { transform: scaleX(-1); }
        .tile.screen video { object-fit: contain; }
        .tile .name { position: absolute; inset-inline-start: 8px; bottom: 8px; background: rgba(0,0,0,.55); padding: 2px 10px; border-radius: 999px; font-size: 13px; }
        .tile .avatar { position: absolute; inset: 0; display: grid; place-items: center; font-size: 40px; font-weight: 700; color: #fff; background: color-mix(in oklab, var(--brand) 45%, #000); }
        .tile.has-video .avatar { display: none; }
        .waiting { text-align: center; color: var(--muted); padding: 8px; }
        .controls { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; padding-bottom: env(safe-area-inset-bottom); }
        .banner { background: #78350f; color: #fef3c7; padding: 8px 12px; border-radius: 10px; font-size: 14px; text-align: center; }
        aside { background: var(--panel); border: 1px solid var(--line); border-radius: 16px; padding: 14px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        aside h2 { margin: 0; font-size: 15px; }
        aside .line { border: 1px solid var(--line); border-radius: 10px; padding: 8px 10px; display: flex; flex-direction: column; gap: 2px; }
        aside .line small { color: var(--muted); font-size: 12px; }
        aside .line p { margin: 4px 0 0; font-size: 13px; color: #d4d4d8; white-space: pre-line; }
        .tag { align-self: flex-start; font-size: 11px; padding: 1px 8px; border-radius: 999px; background: var(--line); }
        aside h2 + h2, aside .line + h2, aside small + h2 { margin-top: 6px; }
        .homework.submitted .tag { background: color-mix(in oklab, var(--brand) 35%, var(--line)); }
        .homework.missed .tag { background: #7f1d1d; color: #fee2e2; }
        .materials { list-style: none; margin: 6px 0 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
        .materials a { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; background: color-mix(in oklab, var(--brand) 14%, transparent); color: #fff; text-decoration: none; font-size: 13px; }
        .materials a:hover, .materials a:focus-visible { background: color-mix(in oklab, var(--brand) 28%, transparent); }
        .materials svg { flex: none; width: 16px; height: 16px; }
        .materials span { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .materials em { font-style: normal; font-size: 11px; color: var(--muted); white-space: nowrap; }
        .rec { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 2px 10px; border-radius: 999px; background: var(--danger); color: #fff; white-space: nowrap; }
        .rec::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #fff; }
        @media (prefers-reduced-motion: no-preference) { .rec::before { animation: rec-pulse 1.6s ease-in-out infinite; } @keyframes rec-pulse { 50% { opacity: .35; } } }
        button.record[aria-pressed="true"] { background: var(--danger); color: #fff; }
        .rec-note { text-align: center; font-size: 13px; color: var(--muted); margin: 0; }
        .may-record { font-size: 13px; color: var(--muted); border-inline-start: 3px solid var(--danger); padding-inline-start: 10px; }
        [hidden] { display: none !important; }
        @media (max-width: 640px) { header .brand { display: none; } .tiles { grid-template-columns: minmax(0, 1fr); } }
    </style>
    <script src="{{ config('classroom.client_js') }}"></script>
</head>
<body data-state="prejoin">
    <header>
        <span class="brand">{{ $brand->name() }}</span>
        <span class="lesson">
            <strong>{{ __($l.'with', ['subject' => $lesson->subject(), 'teacher' => $lesson->teacherName, 'student' => $lesson->studentName]) }}</strong>
            <small>{{ $when }}</small>
        </span>
        <span class="rec" id="rec" role="status" @if (! $recording['running']) hidden @endif>{{ __('classroom::recordings.room.on') }}</span>
        <span class="quality" id="quality"></span>
        @php
            // Anyone in the lesson can tell the academy's safeguarding leads that something is wrong, without leaving it.
            $concernRoute = ['student' => 'filament.family.pages.tell-us', 'teacher' => 'filament.teach.pages.raise-concern'][$seat->role] ?? null;
        @endphp
        @if ($concernRoute && Route::has($concernRoute))
            <a class="concern" href="{{ route($concernRoute) }}" target="_blank" rel="noopener">{{ __($l.'room.concern') }}</a>
        @endif
        <span class="chip">{{ __($l.'roles.'.$seat->role) }}</span>
    </header>

    @php
        $materials = collect($plan)->flatMap(fn ($line) => $line->materials)->unique('id')->values();
        $icons = [
            'pdf' => 'M14 3H7.5A1.5 1.5 0 0 0 6 4.5v15A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V7l-4-4ZM14 3v4h4M9 13h6M9 17h4',
            'audio' => 'M9 18V6l10-2v12M9 18a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm10-2a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z',
            'video' => 'M4.5 6h9A1.5 1.5 0 0 1 15 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 3 16.5v-9A1.5 1.5 0 0 1 4.5 6ZM15 10.5l6-3.5v10l-6-3.5',
            'image' => 'M4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5ZM3 16l5-5 4 4 3-3 6 6M15.5 9.5h.01',
            'link' => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
        ];
    @endphp
    <main class="{{ $mushaf ? 'with-plan with-mushaf' : ($seat->role !== 'student' || $materials->isNotEmpty() ? 'with-plan' : '') }}">
        <div class="stage">
            <section class="prejoin" id="prejoin">
                <h1>{{ __($l.'check.title') }}</h1>
                @if ($seat->role === 'observer')
                    <p>{{ __($l.'check.observer') }}</p>
                @else
                    <p>{{ __($l.'check.hint') }}</p>
                    <div class="preview"><video id="preview" autoplay muted playsinline></video></div>
                    <p class="err" id="device-error" hidden>{{ __($l.'check.camera_off') }}</p>
                    <div><small style="color:var(--muted)">{{ __($l.'check.mic') }}</small><div class="meter"><i id="meter"></i></div></div>
                @endif
                @if ($recording['mode'] !== 'off' && $recording['missing'] === [] && $recording['controls'] === false)
                    <p class="may-record">{{ __('classroom::recordings.room.may_record') }}</p>
                @endif
                <p class="err" id="join-error" hidden></p>
                <div class="row">
                    <button class="primary" id="join" data-label="{{ __($l.'check.join') }}">{{ __($l.'check.join') }}</button>
                    @if ($seat->role !== 'observer') <button id="join-audio">{{ __($l.'check.join_audio') }}</button> @endif
                </div>
            </section>

            <div class="banner" id="reconnecting" hidden>{{ __($l.'room.reconnecting') }}</div>
            <div class="waiting" id="waiting" hidden>{{ __($l.'room.waiting', ['name' => $other]) }}</div>
            <div class="tiles" id="tiles" hidden></div>
            <div class="controls" id="controls" hidden>
                @if ($seat->role !== 'observer')
                    <button id="mic" class="mute" aria-pressed="true">{{ __($l.'room.mic') }}</button>
                    <button id="camera" class="mute" aria-pressed="true">{{ __($l.'room.camera') }}</button>
                    <button id="screen" class="toggle" aria-pressed="false">{{ __($l.'room.screen') }}</button>
                @endif
                <button id="audio-only" class="toggle" aria-pressed="false">{{ __($l.'room.audio_only') }}</button>
                @if ($recording['controls'])
                    <button id="record" class="record" aria-pressed="{{ $recording['running'] ? 'true' : 'false' }}" @disabled($recording['missing'] !== [] && ! $recording['running'])>
                        {{ $recording['running'] ? __('classroom::recordings.room.stop') : __('classroom::recordings.room.start') }}
                    </button>
                @endif
                <button class="danger" id="leave">{{ __($l.'room.leave') }}</button>
            </div>
            @if ($recording['controls'] && $recording['missing'] !== [])
                <p class="rec-note" id="rec-missing">{{ __('classroom::recordings.errors.consent_missing', ['names' => implode('، ', $recording['missing'])]) }}</p>
            @endif
            <p class="err rec-note" id="rec-error" hidden></p>

            @if ($mushaf)
                {{-- A lesson on Zoom or Meet opens in a tab of its own, so the mushaf stays open here beside it. --}}
                <section class="notice" id="outside" hidden>
                    <h1>{{ __($l.'external.opened') }}</h1>
                    <div class="row"><a class="button primary" href="{{ $goUrl }}" target="_blank" rel="noopener">{{ __($l.'external.again') }}</a></div>
                </section>
            @endif

            <section class="notice" id="left" hidden>
                <h1>{{ __($l.'room.left') }}</h1>
                <div class="row"><button class="primary" id="rejoin">{{ __($l.'room.rejoin') }}</button></div>
            </section>
        </div>

        @if ($mushaf || $seat->role !== 'student' || $materials->isNotEmpty())
            <aside>
                @if ($mushaf) @include('classroom::mushaf') @endif
            @if ($seat->role !== 'student')
                <h2>{{ __($l.'plan.title') }}</h2>
                @forelse ($plan as $line)
                    <div class="line">
                        <span class="tag">{{ __($l.'plan.'.$line->reason) }}</span>
                        <strong>{{ $line->title }}</strong>
                        <small>{{ $line->path }}</small>
                        @if ($line->objectives) <p>{{ $line->objectives }}</p> @endif
                        @include('classroom::materials', ['items' => $line->materials])
                    </div>
                @empty
                    <small style="color:var(--muted)">{{ __($l.'plan.none') }}</small>
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
                    <small style="color:var(--muted)">{{ __($l.'homework.none') }}</small>
                @endif
            @elseif ($materials->isNotEmpty())
                <h2>{{ __($l.'materials.title') }}</h2>
                <small style="color:var(--muted)">{{ __($l.'materials.hint') }}</small>
                @include('classroom::materials', ['items' => $materials->all()])
            @endif
            </aside>
        @endif
    </main>

    <script>
    (() => {
        const cfg = {{ Illuminate\Support\Js::from($cfg) }};
        const $ = (id) => document.getElementById(id);
        const LK = window.LivekitClient;
        const publishes = cfg.role !== 'observer';
        let room = null, preview = null, audioOnly = false, meterTimer = null;

        const setState = (s) => { document.body.dataset.state = s;
            $('prejoin').hidden = s !== 'prejoin' && s !== 'joining';
            $('tiles').hidden = $('controls').hidden = s !== 'live';
            $('left').hidden = s !== 'left';
            if ($('outside')) $('outside').hidden = s !== 'outside';
            if (s !== 'live') { $('waiting').hidden = true; $('reconnecting').hidden = true; }
        };

        async function startPreview() {
            if (!publishes) return;
            try {
                preview = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                $('preview').srcObject = preview;
                const ctx = new AudioContext(), src = ctx.createMediaStreamSource(preview), an = ctx.createAnalyser();
                src.connect(an); const buf = new Uint8Array(an.fftSize);
                meterTimer = setInterval(() => { an.getByteTimeDomainData(buf); let m = 0; for (const v of buf) m = Math.max(m, Math.abs(v - 128));
                    $('meter').style.width = Math.min(100, m * 1.6) + '%'; }, 100);
            } catch (e) { $('device-error').hidden = false; }
        }
        function stopPreview() { clearInterval(meterTimer); preview?.getTracks().forEach(t => t.stop()); preview = null; }

        function tileFor(participant, source = 'camera') {
            const key = participant.identity + ':' + source;
            let tile = document.querySelector(`.tile[data-key="${CSS.escape(key)}"]`);
            if (!tile) {
                tile = document.createElement('div');
                tile.className = 'tile' + (participant === room.localParticipant ? ' local' : '') + (source === 'screen' ? ' screen' : '');
                tile.dataset.key = key; tile.dataset.identity = participant.identity;
                const name = participant === room.localParticipant ? cfg.you : (participant.name || participant.identity);
                tile.innerHTML = `<div class="avatar"></div><video autoplay playsinline ${participant === room.localParticipant ? 'muted' : ''}></video><span class="name"></span>`;
                tile.querySelector('.avatar').textContent = (name || '?').trim().charAt(0);
                tile.querySelector('.name').textContent = name;
                $('tiles').appendChild(tile);
            }
            return tile;
        }
        function refreshWaiting() {
            const others = [...room.remoteParticipants.values()];
            $('waiting').hidden = others.length > 0;
        }
        function attach(track, participant) {
            if (track.kind === 'audio') { if (participant !== room.localParticipant) document.body.appendChild(track.attach()); return; }
            const tile = tileFor(participant, track.source === LK.Track.Source.ScreenShare ? 'screen' : 'camera');
            track.attach(tile.querySelector('video')); tile.classList.add('has-video');
        }
        function detach(track, participant) {
            track.detach().forEach(el => { if (el.tagName === 'AUDIO') el.remove(); });
            const key = participant.identity + ':' + (track.source === LK.Track.Source.ScreenShare ? 'screen' : 'camera');
            const tile = document.querySelector(`.tile[data-key="${CSS.escape(key)}"]`);
            if (tile) { tile.classList.remove('has-video'); if (track.source === LK.Track.Source.ScreenShare) tile.remove(); }
        }
        function syncButtons() {
            if (!publishes || !room) return;
            const lp = room.localParticipant;
            $('mic').setAttribute('aria-pressed', String(lp.isMicrophoneEnabled));
            $('camera').setAttribute('aria-pressed', String(lp.isCameraEnabled));
            $('screen').setAttribute('aria-pressed', String(lp.isScreenShareEnabled));
        }
        async function setAudioOnly(on) {
            audioOnly = on; $('audio-only').setAttribute('aria-pressed', String(on));
            if (publishes && on) await room.localParticipant.setCameraEnabled(false);
            for (const p of room.remoteParticipants.values()) for (const pub of p.videoTrackPublications.values()) pub.setSubscribed(!on);
            syncButtons();
        }

        async function join(withVideo) {
            $('join-error').hidden = true; $('join').disabled = true; $('join').textContent = cfg.joining; setState('joining');
            let ticket;
            try {
                const res = await fetch(cfg.ticketUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
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
                .on(LK.RoomEvent.TrackMuted, (pub, p) => { if (pub.kind === 'video') tileFor(p, pub.source === LK.Track.Source.ScreenShare ? 'screen' : 'camera').classList.remove('has-video'); syncButtons(); })
                .on(LK.RoomEvent.TrackUnmuted, (pub, p) => { if (pub.kind === 'video') tileFor(p, pub.source === LK.Track.Source.ScreenShare ? 'screen' : 'camera').classList.add('has-video'); syncButtons(); })
                .on(LK.RoomEvent.ParticipantConnected, (p) => { tileFor(p); refreshWaiting(); })
                .on(LK.RoomEvent.ParticipantDisconnected, (p) => { document.querySelectorAll(`.tile[data-identity="${CSS.escape(p.identity)}"]`).forEach(t => t.remove()); refreshWaiting(); })
                .on(LK.RoomEvent.ActiveSpeakersChanged, (speakers) => { document.querySelectorAll('.tile').forEach(t => t.classList.toggle('speaking', speakers.some(s => s.identity === t.dataset.identity))); })
                .on(LK.RoomEvent.ConnectionQualityChanged, (q, p) => { if (p === room.localParticipant) { $('quality').dataset.q = q; $('quality').textContent = cfg.quality[q] ?? ''; } })
                .on(LK.RoomEvent.Reconnecting, () => { $('reconnecting').hidden = false; })
                .on(LK.RoomEvent.Reconnected, () => { $('reconnecting').hidden = true; })
                .on(LK.RoomEvent.Disconnected, () => { document.querySelectorAll('.tile').forEach(t => t.remove()); setState('left'); document.dispatchEvent(new CustomEvent('lms:room', { detail: null })); })
                .on(LK.RoomEvent.RecordingStatusChanged, () => showRecording())
                .on(LK.RoomEvent.RoomMetadataChanged, () => { recording = !!roomMeta().recording; showRecording(); });
            try {
                await room.connect(ticket.url, ticket.token);
            } catch (e) { return fail(cfg.providerError); }
            // The mushaf beside the room hears the teacher's turns through it (lms:room).
            document.dispatchEvent(new CustomEvent('lms:room', { detail: room }));
            setState('live');
            if (publishes) tileFor(room.localParticipant);
            room.remoteParticipants.forEach(p => tileFor(p));
            refreshWaiting();
            if (publishes) {
                try { await room.localParticipant.setMicrophoneEnabled(true); } catch (e) {}
                // The academy may have a student come in with the camera off (under-18s, or everyone): they can switch it on.
                if (withVideo && ticket.camera !== false) { try { await room.localParticipant.setCameraEnabled(true); } catch (e) {} }
            }
            if (!withVideo) await setAudioOnly(true);
            syncButtons();
            if ('recording' in roomMeta()) recording = !!roomMeta().recording;
            showRecording();
        }
        // Everyone sees when the lesson is being recorded: LiveKit says so itself, and the room's metadata too.
        const rec = cfg.recording;
        let recording = rec.running;
        function roomMeta() { try { return JSON.parse(room?.metadata || '{}') || {}; } catch (e) { return {}; } }
        function showRecording() {
            const on = recording || !!room?.isRecording;
            $('rec').hidden = !on;
            const btn = $('record');
            if (btn) { btn.setAttribute('aria-pressed', on ? 'true' : 'false'); btn.textContent = on ? rec.labels.stop : rec.labels.start; if (on) btn.disabled = false; }
        }
        $('record') && ($('record').onclick = async () => {
            const btn = $('record'), stop = btn.getAttribute('aria-pressed') === 'true';
            btn.disabled = true; $('rec-error').hidden = true;
            try {
                const res = await fetch(stop ? rec.stopUrl : rec.startUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
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

        $('join').onclick = () => join(true);
        $('join-audio') && ($('join-audio').onclick = () => join(false));
        $('rejoin').onclick = () => { setState('prejoin'); join(!audioOnly); };
        $('leave').onclick = () => room?.disconnect();
        $('audio-only').onclick = () => setAudioOnly(!audioOnly);
        if (publishes) {
            $('mic').onclick = async () => { await room.localParticipant.setMicrophoneEnabled(!room.localParticipant.isMicrophoneEnabled); syncButtons(); };
            $('camera').onclick = async () => { if (audioOnly) await setAudioOnly(false); await room.localParticipant.setCameraEnabled(!room.localParticipant.isCameraEnabled); syncButtons(); };
            $('screen').onclick = async () => { try { await room.localParticipant.setScreenShareEnabled(!room.localParticipant.isScreenShareEnabled); } catch (e) {} syncButtons(); };
        }
        window.addEventListener('beforeunload', () => room?.disconnect());
        startPreview();
    })();
    </script>
</body>
</html>
