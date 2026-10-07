"""The app's sound effects, made here from sine waves (no third-party audio,
nothing to license): run `python3 tool/sounds.py` to write assets/sounds/*.wav."""
import math, os, struct, wave

RATE = 22050
here = os.path.dirname(os.path.abspath(__file__))
out = os.path.join(here, '..', 'assets', 'sounds')
os.makedirs(out, exist_ok=True)


def tone(freq, dur, start=0.0, vol=0.5, decay=6.0, harmonics=(1.0, 0.35, 0.12), glide=0.0, vibrato=0.0):
    """One note: a few harmonics, a quick attack and an exponential fall."""
    n = int(dur * RATE)
    samples = []
    phase = 0.0
    for i in range(n):
        t = i / RATE
        f = freq * (1 + glide * t / dur) * (1 + vibrato * math.sin(2 * math.pi * 6 * t))
        phase += 2 * math.pi * f / RATE
        env = min(1.0, t / 0.006) * math.exp(-decay * t)
        v = sum(a * math.sin(phase * (k + 1)) for k, a in enumerate(harmonics))
        samples.append(vol * env * v)
    return start, samples


def mix(parts, length, echo=0.0):
    buf = [0.0] * int(length * RATE)
    for start, s in parts:
        o = int(start * RATE)
        for i, v in enumerate(s):
            if o + i < len(buf):
                buf[o + i] += v
    if echo:
        d = int(0.09 * RATE)
        for i in range(d, len(buf)):
            buf[i] += buf[i - d] * echo
    peak = max(1e-9, max(abs(v) for v in buf))
    return [v / peak * 0.8 for v in buf]


def write(name, buf):
    fade = int(0.01 * RATE)
    for i in range(fade):
        buf[-1 - i] *= i / fade
    with wave.open(os.path.join(out, name + '.wav'), 'wb') as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(RATE)
        w.writeframes(b''.join(struct.pack('<h', int(max(-1, min(1, v)) * 32767)) for v in buf))


# A soft pop for taps.
write('pop', mix([tone(700, 0.07, glide=0.9, decay=40, harmonics=(1.0, 0.2))], 0.08))

# Points in: the classic two-note coin.
write('coin', mix([tone(988, 0.09, decay=18), tone(1319, 0.32, start=0.08, decay=9)], 0.42))

# The treasure chest: a bright arpeggio and sparkles, with a little echo.
notes = [523.25, 659.25, 783.99, 1046.5, 1318.5, 1568.0]
parts = [tone(f, 0.45, start=i * 0.075, decay=7, vol=0.45) for i, f in enumerate(notes)]
parts += [tone(2093 + 300 * (i % 3), 0.12, start=0.5 + i * 0.06, decay=25, vol=0.18, harmonics=(1.0,)) for i in range(6)]
write('chest', mix(parts, 1.15, echo=0.25))

# Level up: a short fanfare ending on a held note.
fan = [(392.0, 0.0, 0.14), (523.25, 0.13, 0.14), (659.25, 0.26, 0.14), (783.99, 0.39, 0.12), (1046.5, 0.52, 0.75)]
write('levelup', mix([tone(f, d, start=s, decay=3 if d > 0.5 else 9, vol=0.5, vibrato=0.006 if d > 0.5 else 0) for f, s, d in fan]
                     + [tone(523.25, 0.75, start=0.52, decay=3, vol=0.25)], 1.35, echo=0.2))

# A cheer from home: three happy blips and a sparkle.
write('cheer', mix([tone(880, 0.1, start=0.0, decay=14), tone(1108.7, 0.1, start=0.1, decay=14), tone(1318.5, 0.35, start=0.2, decay=7)]
                   + [tone(2637, 0.1, start=0.32 + i * 0.05, decay=30, vol=0.15, harmonics=(1.0,)) for i in range(4)], 0.7, echo=0.2))
print(sorted(os.listdir(out)))
