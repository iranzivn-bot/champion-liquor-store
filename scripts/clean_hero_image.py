"""
Clean near-white background artifacts from hero product images.
Detects background color from edge pixels and removes near-white
reflections/jagged artifacts, with aggressive bottom-region cleanup.

Usage:
    python clean_hero_image.py <input.png> [output.png]
"""

import sys
from PIL import Image
import math


def clean_image(input_path: str, output_path: str | None = None,
                bottom_aggression: float = 1.8,
                edge_samples: int = 50) -> None:
    if output_path is None:
        output_path = input_path

    img = Image.open(input_path).convert("RGBA")
    w, h = img.size
    pixels = img.load()

    # ── 1. Sample edge pixels to find dominant background color ──
    bg_r, bg_g, bg_b = 0, 0, 0
    count = 0

    # Top/bottom edges (avoid center for bottom — bottles sit there)
    for x in range(0, w, max(1, w // edge_samples)):
        for y in (0, h - 1):
            r, g, b, a = pixels[x, y]
            # Skip bottom-center (likely product, not background)
            if y == h - 1 and 0.25 * w < x < 0.75 * w:
                continue
            bg_r += r
            bg_g += g
            bg_b += b
            count += 1

    # Left/right edges
    for y in range(0, h, max(1, h // edge_samples)):
        for x in (0, w - 1):
            r, g, b, a = pixels[x, y]
            bg_r += r
            bg_g += g
            bg_b += b
            count += 1

    bg_r /= count
    bg_g /= count
    bg_b /= count

    # Background color distance threshold
    max_dist = 0.0
    for x in range(0, w, max(1, w // edge_samples)):
        for y in (0, h - 1):
            r, g, b, a = pixels[x, y]
            d = math.sqrt((r - bg_r) ** 2 + (g - bg_g) ** 2 + (b - bg_b) ** 2)
            max_dist = max(max_dist, d)
    for y in range(0, h, max(1, h // edge_samples)):
        for x in (0, w - 1):
            r, g, b, a = pixels[x, y]
            d = math.sqrt((r - bg_r) ** 2 + (g - bg_g) ** 2 + (b - bg_b) ** 2)
            max_dist = max(max_dist, d)

    threshold = max(max_dist * 2.5, 70)
    bottom_zone = int(h * 0.7)  # Bottom 30%
    print(f"Background: ({bg_r:.0f}, {bg_g:.0f}, {bg_b:.0f}), "
          f"threshold={threshold:.0f}")

    # ── 2. Process every pixel ──
    removed = 0
    total = w * h
    for y in range(h):
        for x in range(w):
            r, g, b, a = pixels[x, y]
            if a < 128:
                continue  # already transparent

            dist = math.sqrt((r - bg_r) ** 2 + (g - bg_g) ** 2 + (b - bg_b) ** 2)
            is_bottom = y >= bottom_zone
            effective_threshold = threshold * (bottom_aggression if is_bottom else 1.0)

            is_whiteish = r > 210 and g > 210 and b > 210
            is_green = g > r + 30 and g > b + 20    # Heineken green glass
            is_gold = r > 160 and g > 130 and b < 80 and r - b > 80
            is_dark = r < 40 and g < 40 and b < 40

            make_transparent = False

            if dist < effective_threshold or is_whiteish:
                if not is_green and not is_gold and not is_dark:
                    make_transparent = True
                elif is_whiteish and dist < effective_threshold * 1.5:
                    make_transparent = True

            # Bottom zone: remove any near-white pixel that isn't clearly bottle
            if is_bottom and is_whiteish and not is_green and not is_gold:
                make_transparent = True

            if make_transparent:
                pixels[x, y] = (0, 0, 0, 0)
                removed += 1

    print(f"Removed {removed}/{total} pixels ({100 * removed / total:.1f}%)")
    img.save(output_path, "PNG")
    print(f"Saved: {output_path}")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python clean_hero_image.py <input.png> [output.png]")
        sys.exit(1)
    inp = sys.argv[1]
    out = sys.argv[2] if len(sys.argv) > 2 else None
    clean_image(inp, out)
