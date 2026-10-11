"""Google Play's feature graphic, drawn from tool/icon/store/feature.html (the icon's layers and the
app's own fonts; nothing to license): run `python3 tool/store_art.py` after changing it, and commit
what it writes, tool/icon/store/feature-1024x500.png. Play wants it without transparency.

Uses the same headless Chrome as tool/icon.py (`CHROME=/path/to/chrome` if it is not on the PATH).
"""
import os, struct, subprocess, sys, tempfile, zlib

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from icon import chrome, decode  # noqa: E402

here = os.path.dirname(os.path.abspath(__file__))
store = os.path.join(here, 'icon', 'store')
WIDTH, HEIGHT = 1024, 500


def rgb_png(width, height, rows):
    """Rows of RGBA pixels as an RGB PNG (no alpha channel)."""
    flat = []
    for line in rows:
        rgb = bytearray(width * 3)
        rgb[0::3], rgb[1::3], rgb[2::3] = line[0:width * 4:4], line[1:width * 4:4], line[2:width * 4:4]
        flat.append(rgb)
    chunk = lambda kind, body: struct.pack('>I', len(body)) + kind + body + struct.pack('>I', zlib.crc32(kind + body))
    header = struct.pack('>IIBBBBB', width, height, 8, 2, 0, 0, 0)
    pixels = zlib.compress(b''.join(b'\0' + bytes(line) for line in flat), 9)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', header) + chunk(b'IDAT', pixels) + chunk(b'IEND', b'')


def main():
    binary = chrome()
    out = os.path.join(store, 'feature-1024x500.png')
    with tempfile.TemporaryDirectory() as tmp:
        shot = os.path.join(tmp, 'feature.png')
        # The window is taller than the page: headless Chrome's viewport is a little shorter than its window.
        args = [binary, '--headless', '--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1',
                '--allow-file-access-from-files', '--default-background-color=00000000', f'--user-data-dir={tmp}/profile',
                f'--window-size={WIDTH},{HEIGHT + 200}', '--virtual-time-budget=3000',
                f'--screenshot={shot}', 'file://' + os.path.join(store, 'feature.html')]
        if hasattr(os, 'geteuid') and os.geteuid() == 0:
            args.insert(1, '--no-sandbox')
        subprocess.run(args, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=120)
        with open(shot, 'rb') as f:
            width, height, rows = decode(f.read())
    assert width >= WIDTH and height >= HEIGHT, f'Chrome drew {width}x{height}'
    with open(out, 'wb') as f:
        f.write(rgb_png(WIDTH, HEIGHT, rows[:HEIGHT]))
    print(os.path.relpath(out, os.path.join(here, '..')))


if __name__ == '__main__':
    main()
