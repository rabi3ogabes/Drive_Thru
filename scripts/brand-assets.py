"""Renders the Dhawq brand assets from resources/brand/dhawq-mark.svg.

Run after editing the master drawing:  python -I scripts/brand-assets.py
Needs Pillow and Playwright (Chromium). Writes into public/ and the files are committed,
so servers never need this script.
"""
import io
import pathlib
import shutil

from PIL import Image
from playwright.sync_api import sync_playwright

ROOT = pathlib.Path(__file__).resolve().parent.parent
MASTER = ROOT / 'resources' / 'brand' / 'dhawq-mark.svg'
PUBLIC = ROOT / 'public'
FONTS = PUBLIC / 'fonts' / 'guest'
IVORY = '#fcfbf8'
TEAL = '#0f3b3a'
BRASS = '#b08d57'


def font_face(family: str, file: pathlib.Path) -> str:
    return f"@font-face {{ font-family: '{family}'; src: url('{file.as_uri()}') format('woff2'); font-weight: 100 900; }}"


def render(page, html: str, width: int, height: int) -> bytes:
    page.set_viewport_size({'width': width, 'height': height})
    page.set_content(html)
    page.wait_for_timeout(250)
    return page.screenshot(omit_background=True, clip={'x': 0, 'y': 0, 'width': width, 'height': height})


def main() -> None:
    svg = MASTER.read_text(encoding='utf-8').strip()
    (PUBLIC / 'icons').mkdir(parents=True, exist_ok=True)
    shutil.copyfile(MASTER, PUBLIC / 'favicon.svg')

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page(device_scale_factor=1)

        def mark(size: int, padding: int = 0, background: str = 'transparent') -> bytes:
            inner = size - 2 * padding
            html = f'<html><body style="margin:0;background:{background}"><div style="width:{size}px;height:{size}px;display:grid;place-items:center">' \
                   f'{svg.replace("<svg ", f"<svg width={inner} height={inner} ", 1)}</div></body></html>'
            return render(page, html, size, size)

        for name, size in [('apple-touch-icon.png', 180), ('icons/icon-192.png', 192), ('icons/icon-512.png', 512)]:
            (PUBLIC / name).write_bytes(mark(size))

        # Maskable: the mark inside the 80% safe zone on the tile colour, so any mask shape keeps it whole.
        (PUBLIC / 'icons' / 'maskable-512.png').write_bytes(mark(512, padding=51, background='#0d332f'))

        ico = Image.open(io.BytesIO(mark(256))).convert('RGBA')
        ico.save(PUBLIC / 'favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])

        faces = font_face('Fraunces', FONTS / 'fraunces' / 'fraunces-latin-wght-normal.woff2') + \
            font_face('Alexandria', FONTS / 'alexandria' / 'alexandria-arabic-wght-normal.woff2') + \
            font_face('Geist', FONTS / 'geist' / 'geist-latin-wght-normal.woff2')
        og = f'''<html><head><style>{faces}
            body {{ margin:0; width:1200px; height:630px; background:{IVORY}; font-family: Geist, sans-serif; color:{TEAL}; }}
            .wrap {{ height:100%; display:flex; flex-direction:column; justify-content:center; align-items:center; gap:34px; }}
            .row {{ display:flex; align-items:center; gap:34px; }}
            .ar {{ font-family: Alexandria; font-weight:700; font-size:120px; line-height:1; }}
            .bar {{ width:2px; height:84px; background:{TEAL}; opacity:.18; }}
            .en {{ font-family: Fraunces; font-weight:600; font-size:58px; letter-spacing:.18em; }}
            .services {{ font-size:26px; color:#475167; letter-spacing:.02em; }}
            .services b {{ color:{BRASS}; font-weight:500; padding:0 14px; }}
            .services-ar {{ font-family: Alexandria; font-size:26px; color:#475167; direction: rtl; }}
            .services-ar b {{ color:{BRASS}; font-weight:500; padding:0 14px; }}
            .rule {{ position:absolute; inset:24px; border:1px solid rgba(15,59,58,.10); border-radius:28px; }}
        </style></head><body><div class="rule"></div><div class="wrap">
            <div class="row">{svg.replace("<svg ", "<svg width=148 height=148 ", 1)}<span class="ar">ذوق</span><span class="bar"></span><span class="en">DHAWQ</span></div>
            <div class="services">Drive-Thru<b>·</b>Order-Ready Call<b>·</b>Rewards</div>
            <div class="services-ar">درايف ثرو<b>·</b>نداء جاهزية الطلب<b>·</b>المكافآت</div>
        </div></body></html>'''
        # Loaded from a file so the local font files may be read (an inline page can't).
        page.set_viewport_size({'width': 1200, 'height': 630})
        temp = ROOT / 'storage' / 'framework' / 'brand-og.html'
        temp.write_text(og, encoding='utf-8')
        page.goto(temp.as_uri())
        page.evaluate('document.fonts.ready')
        page.wait_for_timeout(300)
        (PUBLIC / 'images' / 'site').mkdir(parents=True, exist_ok=True)
        page.screenshot(path=str(PUBLIC / 'images' / 'site' / 'og.png'))
        temp.unlink()
        browser.close()

    print('Brand assets written: favicon.svg, favicon.ico, apple-touch-icon.png, icons/*.png, images/site/og.png')


if __name__ == '__main__':
    main()
