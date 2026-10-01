#!/usr/bin/env python3
"""Generates the BAFO launcher icons, notification icon and splash art.

Run from apps/mobile:  python3 tool/brand/generate_brand_assets.py
Needs Python 3 + Pillow; iOS sizes are resized with macOS `sips`.

Sources (brand package, repo root):
  assets/brand/02_logo_files/vector-source/icon_master.svg   (geometry below)
  assets/brand/03_app_icons/dark/                            (flat launcher icons)

Why the mark is re-rendered instead of reusing the delivered adaptive foreground:
the delivered 432 px foreground reaches a 152 px (38 dp) radius, beyond the 33 dp
adaptive-icon safe zone, so circular masks clip the green tips
(docs/01_findings/05_brand.md §2.7). We draw the mark from its vector geometry so
its farthest point sits inside the safe zone.
"""

from __future__ import annotations

import json
import math
import shutil
import subprocess
from pathlib import Path

from PIL import Image, ImageDraw

MOBILE = Path(__file__).resolve().parents[2]
REPO = MOBILE.parents[1]
BRAND = REPO / "assets" / "brand"
DARK_ICONS = BRAND / "03_app_icons" / "dark"
RES = MOBILE / "android" / "app" / "src" / "main" / "res"
IOS_ICONSET = MOBILE / "ios" / "Runner" / "Assets.xcassets" / "AppIcon.appiconset"
SPLASH_DIR = MOBILE / "assets" / "splash"
IN_APP_BRAND = MOBILE / "assets" / "brand"

# icon_master.svg geometry (viewBox 0 0 200 200). Drawn in this order: red below green.
RED = (0xB3, 0x26, 0x1E, 255)
GREEN = (0x0E, 0x9F, 0x6E, 255)
WHITE = (255, 255, 255, 255)
RED_LINE = ([(55, 160), (100, 100), (145, 160)], 22)
GREEN_LINE = ([(45, 45), (100, 135), (155, 45)], 32)
CENTER = (100.0, 100.0)
# Farthest painted point from the centre: the green caps, sqrt(55^2 + 55^2) + 32/2.
MARK_RADIUS = math.hypot(55, 55) + 16  # ~93.8 units
MARK_EXTENT = 142.0  # bounding box of the painted mark (29..171 on both axes)
SUPERSAMPLE = 4

# Android density buckets: scale factor relative to mdpi.
DENSITIES = {"mdpi": 1, "hdpi": 1.5, "xhdpi": 2, "xxhdpi": 3, "xxxhdpi": 4}


def _polyline(draw: ImageDraw.ImageDraw, points, width, fill, scale, offset) -> None:
    """Round-capped, round-joined polyline (matches the SVG stroke style)."""
    pts = [(offset[0] + x * scale, offset[1] + y * scale) for x, y in points]
    w = width * scale
    draw.line(pts, fill=fill, width=round(w))
    r = w / 2
    for x, y in pts:
        draw.ellipse((x - r, y - r, x + r, y + r), fill=fill)


def render_mark(canvas: int, radius_px: float, *, mono: bool = False, knockout: float = 0.0) -> Image.Image:
    """Renders the mark centred on a transparent square canvas.

    radius_px: distance from the centre to the mark's farthest painted point.
    mono: white silhouette (themed icon / notification icon).
    knockout: gap (in SVG units) cut around the green chevron so the two chevrons
      stay readable as a silhouette instead of merging into an "X" (§2.5).
    """
    big = canvas * SUPERSAMPLE
    img = Image.new("RGBA", (big, big), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    scale = radius_px * SUPERSAMPLE / MARK_RADIUS
    offset = (big / 2 - CENTER[0] * scale, big / 2 - CENTER[1] * scale)
    red = WHITE if mono else RED
    green = WHITE if mono else GREEN
    _polyline(draw, RED_LINE[0], RED_LINE[1], red, scale, offset)
    if knockout > 0:
        clear = (0, 0, 0, 0)
        _polyline(draw, GREEN_LINE[0], GREEN_LINE[1] + 2 * knockout, clear, scale, offset)
        # Also clear the inside of the green V, otherwise the red apex survives there
        # as a detached speck in the silhouette.
        draw.polygon([(offset[0] + x * scale, offset[1] + y * scale) for x, y in GREEN_LINE[0]], fill=clear)
    _polyline(draw, GREEN_LINE[0], GREEN_LINE[1], green, scale, offset)
    return img.resize((canvas, canvas), Image.LANCZOS)


def write_png(img: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    img.save(path, optimize=True)
    print(f"  {path.relative_to(MOBILE)}  {img.size[0]}x{img.size[1]}")


def android_launcher() -> None:
    print("Android launcher icons")
    # Legacy (pre-API 26) icons: the delivered flat dark icons, 48 dp.
    legacy_sources = {
        "mdpi": DARK_ICONS / "web-favicon" / "appicon_48.png",
        "hdpi": DARK_ICONS / "android" / "appicon_72.png",
        "xhdpi": DARK_ICONS / "android" / "appicon_96.png",
        "xxhdpi": DARK_ICONS / "android" / "appicon_144.png",
        "xxxhdpi": DARK_ICONS / "android" / "appicon_192.png",
    }
    for bucket, src in legacy_sources.items():
        dst = RES / f"mipmap-{bucket}" / "ic_launcher.png"
        shutil.copyfile(src, dst)
        print(f"  {dst.relative_to(MOBILE)}  (copied from {src.relative_to(REPO)})")

    # Adaptive layers: 108 dp canvas, safe zone = 66 dp circle (33 dp radius).
    # Keep the farthest point at 31 dp: inside the safe zone with a small margin.
    for bucket, factor in DENSITIES.items():
        canvas = round(108 * factor)
        radius = 31 * factor
        write_png(render_mark(canvas, radius), RES / f"mipmap-{bucket}" / "ic_launcher_foreground.png")
        write_png(
            render_mark(canvas, radius, mono=True, knockout=5),
            RES / f"mipmap-{bucket}" / "ic_launcher_monochrome.png",
        )


def android_notification_icon() -> None:
    print("Android notification icon (white silhouette)")
    # 24 dp status-bar icon; 2 dp padding, so the mark's bounding box spans 20 dp.
    for bucket, factor in DENSITIES.items():
        canvas = round(24 * factor)
        radius = (20 * factor) * MARK_RADIUS / MARK_EXTENT
        write_png(render_mark(canvas, radius, mono=True, knockout=6), RES / f"drawable-{bucket}" / "ic_stat_bafo.png")


def ios_app_icon() -> None:
    print("iOS AppIcon (opaque, from the dark 1024 master)")
    contents = json.loads((IOS_ICONSET / "Contents.json").read_text())
    master = DARK_ICONS / "ios" / "appicon_1024.png"
    delivered = {
        1024: master,
        180: DARK_ICONS / "ios" / "appicon_180.png",
        152: DARK_ICONS / "ios" / "appicon_152.png",
        120: DARK_ICONS / "ios" / "appicon_120.png",
    }
    for image in contents["images"]:
        base = float(image["size"].split("x")[0])
        pixels = round(base * int(image["scale"].rstrip("x")))
        dst = IOS_ICONSET / image["filename"]
        if pixels in delivered:
            shutil.copyfile(delivered[pixels], dst)
            origin = "delivered"
        else:
            shutil.copyfile(master, dst)
            subprocess.run(["sips", "-Z", str(pixels), str(dst)], check=True, capture_output=True)
            origin = "sips from 1024"
        with Image.open(dst) as check:
            assert check.size == (pixels, pixels), dst
            assert check.mode == "RGB", f"{dst} must be opaque (no alpha) for the App Store"
        print(f"  {dst.relative_to(MOBILE)}  {pixels}x{pixels} ({origin})")


def splash_art() -> None:
    print("Splash art (colour mark for the charcoal splash)")
    # Pre-Android 12 and iOS: 4x image; the mark shows at ~120 dp.
    write_png(render_mark(576, 240), SPLASH_DIR / "splash_mark.png")
    # Android 12+: 1152 px icon (288 dp); keep the art inside the 768 px mask circle.
    write_png(render_mark(1152, 300), SPLASH_DIR / "splash_mark_android12.png")


def in_app_mark() -> None:
    print("In-app vector mark")
    dst = IN_APP_BRAND / "mark.svg"
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(BRAND / "02_logo_files" / "vector-source" / "icon_master.svg", dst)
    print(f"  {dst.relative_to(MOBILE)}  (copied from icon_master.svg)")


if __name__ == "__main__":
    android_launcher()
    android_notification_icon()
    ios_app_icon()
    splash_art()
    in_app_mark()
