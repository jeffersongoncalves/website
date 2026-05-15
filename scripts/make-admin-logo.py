"""Render admin logo PNG (560x100, 2x for retina). Single source of truth."""

from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

OUT = Path(__file__).resolve().parents[1] / "resources" / "images" / "admin-logo.png"
W, H = 560, 100
PAD = 4

FONT_BOLD_CANDIDATES = [
    "C:/Windows/Fonts/seguibl.ttf",
    "C:/Windows/Fonts/segoeuib.ttf",
    "C:/Windows/Fonts/arialbd.ttf",
]
FONT_REG_CANDIDATES = [
    "C:/Windows/Fonts/segoeui.ttf",
    "C:/Windows/Fonts/arial.ttf",
]


def load_font(paths, size):
    for p in paths:
        if Path(p).exists():
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


def gradient_rect(img, box, c1, c2):
    x0, y0, x1, y1 = box
    w, h = x1 - x0, y1 - y0
    grad = Image.new("RGB", (w, h), c1)
    px = grad.load()
    for y in range(h):
        for x in range(w):
            t = (x + y) / (w + h)
            r = int(c1[0] + (c2[0] - c1[0]) * t)
            g = int(c1[1] + (c2[1] - c1[1]) * t)
            b = int(c1[2] + (c2[2] - c1[2]) * t)
            px[x, y] = (r, g, b)
    mask = Image.new("L", (w, h), 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, w - 1, h - 1), radius=18, fill=255)
    img.paste(grad, (x0, y0), mask)


def main():
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    box_size = H - 2 * PAD
    box = (PAD, PAD, PAD + box_size, PAD + box_size)
    gradient_rect(img, box, (245, 158, 11), (180, 83, 9))

    jg_font = load_font(FONT_BOLD_CANDIDATES, 44)
    jg = "JG"
    bbox = draw.textbbox((0, 0), jg, font=jg_font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    tx = box[0] + (box_size - tw) / 2 - bbox[0]
    ty = box[1] + (box_size - th) / 2 - bbox[1]
    draw.text((tx, ty), jg, font=jg_font, fill=(255, 255, 255, 255))

    name_x = box[2] + 16
    name_font = load_font(FONT_BOLD_CANDIDATES, 32)
    sub_font = load_font(FONT_REG_CANDIDATES, 26)

    draw.text((name_x, 14), "Jefferson", font=name_font, fill=(17, 24, 39, 255))
    draw.text((name_x, 54), "Gonçalves", font=sub_font, fill=(107, 114, 128, 255))

    OUT.parent.mkdir(parents=True, exist_ok=True)
    img.save(OUT, "PNG", optimize=True)
    print(f"wrote {OUT} ({OUT.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
