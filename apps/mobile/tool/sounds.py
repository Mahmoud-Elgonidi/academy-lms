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

# The islands' sounds (the games), as the web makes them with Web Audio: a few
# shapes of wave, gliding notes, and filtered noise for the sea and the wind.
import random


def wave_at(kind, phase):
    x = (phase / (2 * math.pi)) % 1.0
    if kind == 'triangle':
        return 4 * abs(x - 0.5) - 1
    if kind == 'square':
        return 1.0 if x < 0.5 else -1.0
    if kind == 'sawtooth':
        return 2 * x - 1
    return math.sin(phase)


def note(f1, f2, start, dur, kind='sine', vol=0.2, rate=RATE):
    """A note from f1 to f2 (exponentially), a quick rise and an exponential fall, like the web's tone()."""
    n = int(dur * rate)
    out_, phase = [], 0.0
    for i in range(n):
        t = i / rate
        f = f1 * ((f2 / f1) ** (t / dur)) if f2 else f1
        phase += 2 * math.pi * f / rate
        env = min(1.0, t / 0.015) * math.exp(-5.5 * t / dur)
        out_.append(vol * env * wave_at(kind, phase))
    return start, out_


def whoosh(start, dur, f1, f2, vol, rate=RATE, seed=1):
    """Band-passed noise sweeping from f1 to f2 (the web's noise())."""
    rnd = random.Random(seed)
    n = int(dur * rate)
    out_, y1, y2, x1, x2 = [], 0.0, 0.0, 0.0, 0.0
    for i in range(n):
        t = i / rate
        f = f1 * ((f2 / f1) ** (t / dur))
        w = 2 * math.pi * f / rate
        alpha = math.sin(w) / (2 * 1.2)
        b0, b2, a0, a1, a2 = alpha, -alpha, 1 + alpha, -2 * math.cos(w), 1 - alpha
        x = rnd.uniform(-1, 1)
        y = (b0 * x + b2 * x2 - a1 * y1 - a2 * y2) / a0
        x2, x1, y2, y1 = x1, x, y1, y
        rise = min(1.0, t / (dur * 0.4))
        env = rise if t < dur * 0.4 else math.exp(-4 * (t - dur * 0.4) / dur)
        out_.append(vol * 3 * env * y)
    return start, out_


def effect(name, parts, length):
    write(name, mix(parts, length, echo=0.15))


effect('island_zoom', [whoosh(0, 0.7, 300, 3000, 0.25)] + [note(f, 0, 0.2 + i * 0.07, 0.4, 'triangle', 0.12) for i, f in enumerate([523, 659, 784, 1047, 1319])], 1.0)
effect('island_back', [whoosh(0, 0.5, 2500, 300, 0.2, seed=2)] + [note(f, 0, i * 0.07, 0.3, 'triangle', 0.09) for i, f in enumerate([1047, 784, 659])], 0.7)
effect('island_locked', [note(330, 0, 0, 0.12, 'triangle', 0.16), note(262, 0, 0.12, 0.25, 'triangle', 0.14), whoosh(0.05, 0.2, 4000, 6000, 0.05, seed=3)], 0.45)
effect('egg_knock', [note(320, 180, 0, 0.08, 'triangle', 0.3), whoosh(0.02, 0.1, 2500, 1200, 0.25, seed=4)], 0.2)
effect('egg_hatch', [whoosh(0, 0.25, 3000, 600, 0.5, seed=5)] + [note(f, 0, 0.15 + i * 0.08, 0.35, 'triangle', 0.16) for i, f in enumerate([523, 659, 784, 1047, 1319, 1568])], 0.95)
effect('creature_cheep', [note(2200, 3000, 0, 0.07, 'sine', 0.16), note(2400, 3200, 0.1, 0.07, 'sine', 0.14), note(2600, 2000, 0.2, 0.09, 'sine', 0.12)], 0.35)
effect('round_star', [note(880, 1760, 0, 0.12, 'triangle', 0.16), note(1320, 0, 0.08, 0.25, 'sine', 0.1)], 0.4)
effect('round_win', [note(f, 0, i * 0.11, 0.4, 'triangle', 0.14) for i, f in enumerate([523, 659, 784, 1047])], 0.85)

# The everyday app (not the kids' look): quieter and shorter, so a tap or a
# moment of success feels alive without getting in the way.
write('tap_soft', mix([tone(1250, 0.04, decay=90, vol=0.3, harmonics=(1.0, 0.1))], 0.05))
write('success', mix([tone(784, 0.12, decay=12, vol=0.45), tone(1174.7, 0.45, start=0.1, decay=6, vol=0.45)]
                     + [tone(2349, 0.08, start=0.22 + i * 0.05, decay=30, vol=0.1, harmonics=(1.0,)) for i in range(3)], 0.65, echo=0.15))
effect('swoosh', [whoosh(0, 0.28, 600, 2400, 0.16, seed=6)], 0.32)
effect('send', [whoosh(0, 0.2, 800, 3200, 0.14, seed=7), note(1320, 1760, 0.12, 0.14, 'sine', 0.12)], 0.32)
write('coin_soft', mix([tone(1318.5, 0.07, decay=25, vol=0.3), tone(1760, 0.22, start=0.06, decay=14, vol=0.26)], 0.3))

# Each island's music: notes walking on its own scale over a quiet sea, a loop
# of 24 steps (6.72 s) at the web's pace (280 ms), at a lower rate to stay small.
SCALES = {
    'map': [392, 440, 523.25, 587.33, 659.25, 783.99, 880],
    'major': [523.25, 587.33, 659.25, 783.99, 880, 1046.5, 1174.66],
    'pentatonic': [261.63, 293.66, 329.63, 392, 440, 523.25, 587.33],
    'dorian': [293.66, 329.63, 349.23, 392, 440, 493.88, 523.25],
    'lydian': [349.23, 392, 440, 493.88, 523.25, 587.33, 659.25],
    'phrygian': [164.81, 174.61, 196, 220, 246.94, 261.63, 293.66],
}
MUSIC_RATE = 16000
STEP = 0.28
STEPS = 24


def sea(length, rate, seed):
    """Low-passed noise that swells slowly, ending where it began (a seamless loop)."""
    rnd = random.Random(seed)
    n = int(length * rate)
    out_, y = [], 0.0
    k = 1 - math.exp(-2 * math.pi * 420 / rate)
    for i in range(n):
        y += k * (rnd.uniform(-1, 1) - y)
        swell = 0.6 + 0.4 * math.sin(2 * math.pi * i / n)
        out_.append(0.5 * y * swell)
    return 0.0, out_


for name, scale in SCALES.items():
    rnd = random.Random(name)
    parts, idx = [sea(STEPS * STEP, MUSIC_RATE, len(name))], 3
    for step in range(STEPS):
        at = step * STEP
        bar = step % 16
        if bar in (0, 8):
            parts.append(note(scale[0] / 2, 0, at, 0.9, 'sine', 0.05, MUSIC_RATE))
        if bar in (4, 12):
            parts.append(note(scale[2] / 2, 0, at, 0.7, 'sine', 0.04, MUSIC_RATE))
        if bar % 4 == 3 and rnd.random() < 0.5:
            continue
        idx = max(0, min(len(scale) - 1, idx + rnd.choice([-2, -1, 1, 2, 0])))
        f = scale[idx]
        if name == 'major':
            parts.append(note(f, f * 1.01, at, 0.35, 'sine', 0.035, MUSIC_RATE))
        elif name == 'pentatonic':
            parts.append(note(f, 0, at, 0.22, 'triangle', 0.05, MUSIC_RATE))
        elif name == 'dorian':
            parts.append(note(f, 0, at, 0.16, 'triangle', 0.055, MUSIC_RATE))
        elif name == 'lydian':
            parts.append(note(f * 2, 0, at, 0.6, 'sine', 0.03, MUSIC_RATE))
        elif name == 'phrygian':
            parts.append(note(f, 0, at, 0.5, 'triangle', 0.05, MUSIC_RATE))
        else:
            parts.append(note(f, 0, at, 0.45, 'triangle', 0.035, MUSIC_RATE))
    length = STEPS * STEP
    buf = [0.0] * int(length * MUSIC_RATE)
    for start, smp in parts:
        o = int(start * MUSIC_RATE)
        for i, v in enumerate(smp):
            buf[(o + i) % len(buf)] += v  # what rings past the end wraps to the start, so the loop is seamless
    peak = max(1e-9, max(abs(v) for v in buf))
    buf = [v / peak * 0.5 for v in buf]
    with wave.open(os.path.join(out, 'music_' + name + '.wav'), 'wb') as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(MUSIC_RATE)
        w.writeframes(b''.join(struct.pack('<h', int(max(-1, min(1, v)) * 32767)) for v in buf))

print(sorted(os.listdir(out)))
