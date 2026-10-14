"""The app's icon, drawn here from three SVG layers (tool/icon/: background, mark, monochrome; our
own, nothing to license): run `python3 tool/icon.py` after changing one, and commit what it writes.

Chrome (headless) draws each picture at its own size from the vectors, so small sizes stay sharp:
`CHROME=/path/to/chrome` if it is not on the PATH. Writes
  Android  mipmap-*/ic_launcher.png (rounded) and ic_launcher_round.png for phones before Android 8;
           the adaptive icon's layers (ic_launcher_{background,foreground,monochrome}.png, 108 dp,
           the mark inside the 66 dp every mask keeps; mipmap-anydpi-v26 puts them together);
  iOS      every picture AppIcon.appiconset/Contents.json names, square and without transparency
           (iOS rounds the corners; the App Store refuses an icon with an alpha channel);
  web      icons/Icon-{192,512}.png, the maskable ones (the mark in the middle 80%), the
           apple-touch-icon and favicon.png;
  stores   tool/icon/store/play-512.png for Google Play (Play rounds it);
  launch   the mark alone for the phone's own launch screen, on the product's green (#0D735E):
           Android drawable-*/splash_mark.png (144 dp, centred by launch_background.xml) and
           splash_icon.png (Android 12's 288 dp icon, the mark in its middle half, inside the
           192 dp circle it keeps), iOS LaunchImage.imageset (144 pt). The app's first screen
           (SplashPage) draws the same mark at the same size, so the hand-over does not blink.
"""
import glob, json, os, shutil, struct, subprocess, sys, tempfile, zlib
from concurrent.futures import ThreadPoolExecutor

here = os.path.dirname(os.path.abspath(__file__))
app = os.path.normpath(os.path.join(here, '..'))
layers = os.path.join(here, 'icon')
res = os.path.join(app, 'android', 'app', 'src', 'main', 'res')
ios = os.path.join(app, 'ios', 'Runner', 'Assets.xcassets', 'AppIcon.appiconset')
launch = os.path.join(app, 'ios', 'Runner', 'Assets.xcassets', 'LaunchImage.imageset')
web = os.path.join(app, 'web')
SPLASH = 144  # the launch screen's mark, in dp/pt (SplashPage's AppMark size)

DENSITIES = {'mdpi': 1, 'hdpi': 1.5, 'xhdpi': 2, 'xxhdpi': 3, 'xxxhdpi': 4}
# The adaptive icon shows the middle 72 of its 108 dp: at this scale the foreground looks as the
# full icon does, and the mark (411 of 512 from the middle, feet included) stays in the 66 dp circle.
ADAPTIVE = 72 / 108
MASKABLE = 0.9  # a maskable web icon keeps a circle of 80% of its width


def chrome():
    named = os.environ.get('CHROME')
    if named:
        return named
    for name in ('google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'chrome'):
        if shutil.which(name):
            return shutil.which(name)
    for path in ['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
                 *glob.glob(os.path.expanduser('~/.cache/ms-playwright/chromium-*/chrome-linux/chrome'))]:
        if os.path.exists(path):
            return path
    sys.exit('No Chrome found: set CHROME=/path/to/chrome')


def page(kind, scale=1.0, margin=0.0, radius='0'):
    """One picture as a page: the layers, the mark at [scale], in a shape [margin] in from the edge.
    The picture sits in the page's top corner at its own size (--size, set by render())."""
    url = lambda name: 'file://' + os.path.join(layers, name + '.svg')
    back = f'<img src="{url("background")}">'
    mark = f'<img src="{url("monochrome" if kind == "monochrome" else "mark")}" style="transform:scale({scale})">'
    inner = {'full': back + mark, 'background': back, 'foreground': mark, 'monochrome': mark}[kind]
    return ('<!doctype html><style>html,body{margin:0;background:transparent;overflow:hidden}'
            'body{position:relative;width:var(--size);height:var(--size)}'
            f'div{{position:absolute;inset:{margin * 100:.4f}%;overflow:hidden;border-radius:{radius}}}'
            'img{position:absolute;inset:0;width:100%;height:100%}</style>'
            f'<div>{inner}</div>')


def render(binary, html, size, out, opaque=False):
    """Headless Chrome's page is at least 500 wide and a little shorter than its window, so the
    picture is drawn in a larger window and cut from the page's top corner."""
    with tempfile.TemporaryDirectory() as tmp:
        src = os.path.join(tmp, 'icon.html')
        with open(src, 'w') as f:
            f.write(html.replace('<style>', f'<style>:root{{--size:{size}px}}', 1))
        shot = os.path.join(tmp, 'icon.png')
        args = [binary, '--headless', '--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1',
                '--default-background-color=00000000', f'--user-data-dir={tmp}/profile',
                f'--window-size={max(size, 600)},{size + 200}', f'--screenshot={shot}', 'file://' + src]
        if hasattr(os, 'geteuid') and os.geteuid() == 0:
            args.insert(1, '--no-sandbox')
        subprocess.run(args, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=120)
        with open(shot, 'rb') as f:
            width, height, rows = decode(f.read())
    assert width >= size and height >= size, f'{out}: Chrome drew {width}x{height}, less than {size}'
    os.makedirs(os.path.dirname(out), exist_ok=True)
    with open(out, 'wb') as f:
        f.write(encode(size, [row[:size * 4] for row in rows[:size]], alpha=not opaque))
    return out


def decode(png):
    """An 8-bit RGBA PNG, as Chrome writes them: its size and its rows of pixels, filters undone."""
    pos, idat = 8, b''
    while pos < len(png):
        length, kind = struct.unpack('>I4s', png[pos:pos + 8])
        body = png[pos + 8:pos + 8 + length]
        pos += 12 + length
        if kind == b'IHDR':
            width, height, depth, colour, _, _, interlace = struct.unpack('>IIBBBBB', body)
        elif kind == b'IDAT':
            idat += body
    assert depth == 8 and colour == 6 and interlace == 0, 'expected 8-bit RGBA'
    raw, stride = zlib.decompress(idat), width * 4
    prev, rows, i = bytearray(stride), [], 0
    for _ in range(height):
        kind, line = raw[i], bytearray(raw[i + 1:i + 1 + stride])
        i += 1 + stride
        if kind == 1:
            for x in range(4, stride):
                line[x] = (line[x] + line[x - 4]) & 255
        elif kind == 2:
            line = bytearray((a + b) & 255 for a, b in zip(line, prev))
        elif kind == 3:
            for x in range(stride):
                line[x] = (line[x] + ((line[x - 4] if x >= 4 else 0) + prev[x]) // 2) & 255
        elif kind == 4:
            for x in range(stride):
                left, up = (line[x - 4] if x >= 4 else 0), prev[x]
                corner = prev[x - 4] if x >= 4 else 0
                p = left + up - corner
                pa, pb, pc = abs(p - left), abs(p - up), abs(p - corner)
                line[x] = (line[x] + (left if pa <= pb and pa <= pc else up if pb <= pc else corner)) & 255
        rows.append(line)
        prev = line
    return width, height, rows


def encode(size, rows, alpha):
    """Square rows of RGBA pixels as a PNG: RGBA, or RGB for a picture that must have no transparency."""
    if not alpha:
        flat = []
        for line in rows:
            rgb = bytearray(size * 3)
            rgb[0::3], rgb[1::3], rgb[2::3] = line[0::4], line[1::4], line[2::4]
            flat.append(rgb)
        rows = flat
    chunk = lambda kind, body: struct.pack('>I', len(body)) + kind + body + struct.pack('>I', zlib.crc32(kind + body))
    header = struct.pack('>IIBBBBB', size, size, 8, 6 if alpha else 2, 0, 0, 0)
    pixels = zlib.compress(b''.join(b'\0' + bytes(line) for line in rows), 9)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', header) + chunk(b'IDAT', pixels) + chunk(b'IEND', b'')


def jobs():
    rounded = dict(kind='full', margin=2 / 48, radius='20%')  # an Android icon of before 8: 44 of its 48 dp
    for name, k in DENSITIES.items():
        folder = os.path.join(res, f'mipmap-{name}')
        yield page(**rounded), round(48 * k), os.path.join(folder, 'ic_launcher.png'), False
        yield page('full', margin=2 / 48, radius='50%'), round(48 * k), os.path.join(folder, 'ic_launcher_round.png'), False
        for layer in ('background', 'foreground', 'monochrome'):
            yield page(layer, scale=ADAPTIVE), round(108 * k), os.path.join(folder, f'ic_launcher_{layer}.png'), False
    with open(os.path.join(ios, 'Contents.json')) as f:
        pictures = {i['filename']: round(float(i['size'].split('x')[0]) * int(i['scale'][0]))
                    for i in json.load(f)['images'] if i.get('filename')}
    for filename, size in pictures.items():
        yield page('full'), size, os.path.join(ios, filename), True
    for size in (192, 512):
        yield page('full', radius='22%'), size, os.path.join(web, 'icons', f'Icon-{size}.png'), False
        yield page('full', scale=MASKABLE), size, os.path.join(web, 'icons', f'Icon-maskable-{size}.png'), True
    yield page('full'), 180, os.path.join(web, 'icons', 'apple-touch-icon.png'), True
    yield page('full', radius='22%'), 32, os.path.join(web, 'favicon.png'), False
    yield page('full'), 512, os.path.join(layers, 'store', 'play-512.png'), False
    for name, k in DENSITIES.items():
        folder = os.path.join(res, f'drawable-{name}')
        yield page('foreground'), round(SPLASH * k), os.path.join(folder, 'splash_mark.png'), False
        yield page('foreground', scale=0.5), round(SPLASH * 2 * k), os.path.join(folder, 'splash_icon.png'), False
    for suffix, k in (('', 1), ('@2x', 2), ('@3x', 3)):
        yield page('foreground'), SPLASH * k, os.path.join(launch, f'LaunchImage{suffix}.png'), False


if __name__ == '__main__':
    binary = chrome()
    with ThreadPoolExecutor(max_workers=4) as pool:
        done = list(pool.map(lambda job: render(binary, *job), jobs()))
    for path in done:
        print(os.path.relpath(path, app))
