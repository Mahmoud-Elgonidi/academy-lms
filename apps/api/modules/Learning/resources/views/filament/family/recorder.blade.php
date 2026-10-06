{{-- Records the answer right here (MediaRecorder) and uploads it as the homework's file. $id: the homework. --}}
@php $l = 'learning::family.homework.'; @endphp
<div class="lf-rec" x-data="{
        rec: null, chunks: [], recording: false, secs: 0, timer: null, url: null, failed: false, busy: false,
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
                    const file = new File(this.chunks, 'recording.' + (mime.includes('mp4') ? 'm4a' : 'webm'), { type: mime });
                    this.url = URL.createObjectURL(file);
                    this.busy = true;
                    $wire.upload('files.{{ $id }}', file, () => { this.busy = false }, () => { this.busy = false; this.failed = true });
                };
                this.rec.start();
                this.recording = true; this.secs = 0;
                this.timer = setInterval(() => this.secs++, 1000);
            } catch (e) { this.failed = true }
        },
        stop() { clearInterval(this.timer); this.recording = false; this.rec?.stop() },
    }">
    <div class="lf-rec-row">
        <button type="button" class="lf-rec-btn" x-show="! recording" x-on:click="start()" :disabled="busy">
            <span class="lf-rec-dot"></span><span x-text="url ? @js(__($l.'record_again')) : @js(__($l.'record'))"></span>
        </button>
        <button type="button" class="lf-rec-btn" data-on x-show="recording" x-cloak x-on:click="stop()">
            <span class="lf-rec-stop"></span>{{ __($l.'stop') }} <span x-text="clock()"></span>
        </button>
        <small class="lf-muted" x-show="busy" x-cloak>{{ __($l.'uploading') }}</small>
    </div>
    <audio x-show="url && ! recording" x-cloak :src="url" controls preload="none" style="width: 100%"></audio>
    <small class="lf-err" x-show="failed" x-cloak>{{ __($l.'mic_failed') }}</small>
</div>
