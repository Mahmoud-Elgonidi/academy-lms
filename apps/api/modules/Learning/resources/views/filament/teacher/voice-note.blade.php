{{-- A draft from the teacher's voice note: records here (MediaRecorder, two minutes at most), uploads it, and the page fills what is empty. --}}
@php $v = 'learning::assist.voice.'; $result = $this->voiceDraft; @endphp
<section class="vn" x-data="{
        rec: null, chunks: [], recording: false, secs: 0, timer: null, failed: false, sending: false,
        limit: 120,
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
                    const file = new File(this.chunks, 'note.' + (mime.includes('mp4') ? 'm4a' : 'webm'), { type: mime });
                    this.sending = true;
                    $wire.upload('voiceNote', file,
                        () => $wire.draftFromVoice().finally(() => { this.sending = false }),
                        () => { this.sending = false; this.failed = true });
                };
                this.rec.start();
                this.recording = true; this.secs = 0;
                this.timer = setInterval(() => { this.secs++; if (this.secs >= this.limit) this.stop() }, 1000);
            } catch (e) { this.failed = true }
        },
        stop() { clearInterval(this.timer); this.recording = false; this.rec?.stop() },
    }">
    <style>
        .vn { display: flex; flex-direction: column; gap: .7rem; padding: 1rem 1.1rem; border-radius: var(--radius-xl, .75rem); border: 1px dashed var(--primary-300); background: var(--primary-50); }
        .vn header { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
        .vn h3 { margin: 0; font-size: .95rem; font-weight: 700; display: flex; align-items: center; gap: .4rem; }
        .vn p, .vn small { margin: 0; font-size: .84rem; color: var(--gray-600); }
        .vn-btn { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .95rem; border-radius: 999px; font-weight: 600; font-size: .88rem; background: var(--primary-600); color: white; }
        .vn-btn[data-on] { background: var(--danger-600); }
        .vn-btn:disabled { opacity: .6; }
        .vn-dot { inline-size: .6rem; block-size: .6rem; border-radius: 999px; background: currentColor; }
        .vn-stop { inline-size: .6rem; block-size: .6rem; border-radius: 2px; background: currentColor; }
        .vn-out { display: flex; flex-direction: column; gap: .45rem; padding: .7rem .8rem; border-radius: var(--radius-lg, .5rem); background: white; border: 1px solid var(--gray-200); }
        .vn-out strong { font-size: .82rem; }
        .vn-chips { display: flex; flex-wrap: wrap; gap: .35rem; }
        .vn-chips span { font-size: .75rem; font-weight: 600; padding: .15rem .55rem; border-radius: 999px; background: var(--warning-50); color: var(--warning-700); }
        .vn-out details summary { cursor: pointer; font-size: .8rem; color: var(--gray-500); }
        .vn-err { color: var(--danger-600) !important; }
        @media (prefers-reduced-motion: no-preference) { .vn-btn[data-on] .vn-stop { animation: vn-pulse 1.2s ease-in-out infinite; } }
        @keyframes vn-pulse { 50% { opacity: .35; } }
        .dark .vn { background: color-mix(in oklab, var(--primary-500) 10%, transparent); border-color: var(--primary-700); }
        .dark .vn-out { background: var(--gray-900); border-color: var(--gray-800); }
    </style>
    <header>
        <div>
            <h3><x-filament::icon icon="heroicon-o-microphone" class="h-5 w-5" /> {{ __($v.'title') }}</h3>
            <p>{{ __($v.'hint') }}</p>
        </div>
        <div style="display:flex; align-items:center; gap:.6rem">
            <button type="button" class="vn-btn" x-show="! recording && ! sending" x-on:click="start()">
                <span class="vn-dot"></span>{{ $result ? __($v.'again') : __($v.'record') }}
            </button>
            <button type="button" class="vn-btn" data-on x-show="recording" x-cloak x-on:click="stop()">
                <span class="vn-stop"></span>{{ __($v.'stop') }} <span x-text="clock()"></span>
            </button>
            <small x-show="sending" x-cloak>{{ __($v.'working') }}</small>
        </div>
    </header>
    <small>{{ __($v.'limit') }} · {{ __($v.'kept') }}</small>
    <small class="vn-err" x-show="failed" x-cloak>{{ __($v.'mic_failed') }}</small>

    @if ($result)
        <div class="vn-out">
            @if ($result['unsure'] !== [])
                <strong>{{ __($v.'unsure') }}</strong>
                <div class="vn-chips">@foreach ($result['unsure'] as $field)<span>{{ $field }}</span>@endforeach</div>
            @endif
            @if ($result['left'] !== [])
                <small>{{ __($v.'left', ['fields' => implode(app()->getLocale() === 'ar' ? '، ' : ', ', $result['left'])]) }}</small>
            @endif
            <details>
                <summary>{{ __($v.'heard') }}</summary>
                <p style="white-space:pre-line">{{ $result['heard'] }}</p>
            </details>
        </div>
    @endif
</section>
