#!/usr/bin/env python3
"""Make printable LLAMA Challenge flyers (US Letter PDFs).

  park-flyers.pdf      one page per park; its QR code opens that park's
                       check-in on llamasystem.org (?place=<key>)
  checklist-flyer.pdf  the 24-park checklist; its QR code opens the
                       Challenge page

Needs: pip install qrcode==7.4.2 reportlab==4.2.5 pillow==10.4.0
Usage: python tools/make_flyers.py OUTPUT_FOLDER [park_key ...]
       (give park keys to make flyers for only those parks)
"""
import io
import sys
from pathlib import Path

import qrcode
from qrcode.constants import ERROR_CORRECT_Q
from reportlab.lib.colors import HexColor, white
from reportlab.lib.pagesizes import letter
from reportlab.lib.units import inch
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas

SITE = "https://www.llamasystem.org"
LOGO = Path(__file__).resolve().parent.parent / "site" / "images" / "Untitled70.png"

# The 24 challenge locations, in the order of the Challenge page.
# key (matches ?place= and site/checkin/lib.php), name, town
PARKS = [
    ("abele", "Abele Memorial Park", "Halfmoon"),
    ("barney", "Barney Road Park", "Clifton Park"),
    ("burning_bush", "Burning Bush Park", "Ballston Lake"),
    ("clifton_commons", "Clifton Commons", "Clifton Park"),
    ("collins", "Collins Park", "Clifton Park"),
    ("dwaas", "Dwaas Kill Nature Preserve", "Clifton Park"),
    ("garnsey", "Garnsey Park", "Clifton Park"),
    ("george_smith", "George T. Smith Park", "Jonesville"),
    ("hayes_moe", "Hayes Nature Park at Moe Road", "Clifton Park"),
    ("halfmoon_town", "Halfmoon Town Park", "Halfmoon"),
    ("harris", "Harris Park", "Halfmoon"),
    ("kinns", "Kinns Road Park", "Clifton Park"),
    ("longkill", "Jewett Park - Longkill Road", "Ballston Lake"),
    ("locust_lane", "Locust Lane Park", "Clifton Park"),
    ("mohawk_landing", "Mohawk Landing Nature Preserve", ""),
    ("shenantaha", "Shenantaha Creek Park", "Ballston Spa"),
    ("ushers", "Ushers Road State Park", "Ballston Lake"),
    ("veterans", "Veterans Memorial Park", "Jonesville"),
    ("towpathLock7Lookout", "Lock 7 & Dam Overlook", "Rexford"),
    ("ferry_road", "Vischer Ferry Preserve - Ferry Road", "Clifton Park"),
    ("whipple", "Vischer Ferry Preserve - Whipple Truss Bridge", "Clifton Park"),
    ("clutes", "Vischer Ferry Preserve - Clute's Dry Dock", "Clifton Park"),
    ("woodcock", "Woodcock Preserve", "Clifton Park"),
    ("zim_coons", "Zim Smith Trail - Coons Crossing", "Mechanicville"),
]

GREEN = HexColor("#2c7a4b")
DARK = HexColor("#1f2a22")
MUTED = HexColor("#4f5d53")
PAGE_W, PAGE_H = letter

# Shorter names for the two-column checklist.
SHORT = {
    "ferry_road": "Vischer Ferry - Ferry Road",
    "whipple": "Vischer Ferry - Whipple Truss Bridge",
    "clutes": "Vischer Ferry - Clute's Dry Dock",
}
MARGIN = 0.5 * inch


def qr_image(url):
    qr = qrcode.QRCode(error_correction=ERROR_CORRECT_Q, box_size=20, border=2)
    qr.add_data(url)
    qr.make(fit=True)
    buf = io.BytesIO()
    qr.make_image(fill_color="black", back_color="white").save(buf, format="PNG")
    buf.seek(0)
    return ImageReader(buf)


def fit_font(c, text, font, max_size, max_width, min_size=18):
    size = max_size
    while size > min_size and c.stringWidth(text, font, size) > max_width:
        size -= 1
    return size


def wrap(c, text, font, size, max_width):
    """Split text into lines that fit max_width."""
    words, lines, line = text.split(), [], ""
    for w in words:
        test = (line + " " + w).strip()
        if c.stringWidth(test, font, size) <= max_width:
            line = test
        else:
            lines.append(line)
            line = w
    if line:
        lines.append(line)
    return lines


def header(c, subtitle):
    band = 1.55 * inch
    c.setFillColor(GREEN)
    c.rect(0, PAGE_H - band, PAGE_W, band, stroke=0, fill=1)
    logo = 1.2 * inch
    c.setFillColor(white)
    cx, cy = MARGIN + logo / 2, PAGE_H - band / 2
    c.circle(cx, cy, logo / 2 + 3, stroke=0, fill=1)
    # The logo is a round badge on a square image: clip it to a circle.
    c.saveState()
    clip = c.beginPath()
    clip.circle(cx, cy, logo / 2 - 1)
    c.clipPath(clip, stroke=0, fill=0)
    c.drawImage(str(LOGO), MARGIN, cy - logo / 2, logo, logo, mask="auto")
    c.restoreState()
    x = MARGIN + logo + 0.3 * inch
    c.setFillColor(white)
    c.setFont("Helvetica-Bold", 34)
    c.drawString(x, PAGE_H - 0.78 * inch, "THE LLAMA CHALLENGE")
    c.setFont("Helvetica", 16)
    c.drawString(x, PAGE_H - 1.15 * inch, subtitle)
    return PAGE_H - band


def footer(c):
    c.setFillColor(GREEN)
    c.rect(0, 0, PAGE_W, 0.75 * inch, stroke=0, fill=1)
    c.setFillColor(white)
    c.setFont("Helvetica", 10.5)
    c.drawCentredString(PAGE_W / 2, 0.45 * inch,
                        "Created by the Robotic Renovators, a Shenendehowa Middle School FIRST LEGO League team.")
    c.drawCentredString(PAGE_W / 2, 0.25 * inch,
                        "Questions: llamasystem@gmail.com   ·   www.llamasystem.org")


def park_page(c, key, name, town, number):
    top = header(c, "Parks, trails and preserves · Location %d of %d" % (number, len(PARKS)))
    width = PAGE_W - 2 * MARGIN

    # Park name, wrapped to at most two lines
    size = fit_font(c, name, "Helvetica-Bold", 40, width, min_size=30)
    if c.stringWidth(name, "Helvetica-Bold", size) > width and " - " in name:
        # Break long names at the dash: "Vischer Ferry Preserve" / "Whipple Truss Bridge"
        lines = name.split(" - ", 1)
        size = min(fit_font(c, line, "Helvetica-Bold", 40, width, min_size=24) for line in lines)
    else:
        lines = wrap(c, name, "Helvetica-Bold", size, width)
    y = top - 0.75 * inch
    c.setFillColor(DARK)
    c.setFont("Helvetica-Bold", size)
    for line in lines[:2]:
        c.drawCentredString(PAGE_W / 2, y, line)
        y -= size * 1.15
    if town:
        c.setFillColor(MUTED)
        c.setFont("Helvetica", 18)
        c.drawCentredString(PAGE_W / 2, y + 4, town)
        y -= 26

    # QR code
    url = "%s/?place=%s" % (SITE, key)
    qr_size = 3.85 * inch
    y -= 0.15 * inch
    c.setFillColor(GREEN)
    c.setFont("Helvetica-Bold", 24)
    c.drawCentredString(PAGE_W / 2, y, "Scan to log your visit")
    y -= 0.32 * inch + qr_size
    c.setStrokeColor(GREEN)
    c.setLineWidth(3)
    c.roundRect((PAGE_W - qr_size) / 2 - 8, y - 8, qr_size + 16, qr_size + 16, 10, stroke=1, fill=0)
    c.drawImage(qr_image(url), (PAGE_W - qr_size) / 2, y, qr_size, qr_size)
    c.setFillColor(MUTED)
    c.setFont("Helvetica", 11)
    c.drawCentredString(PAGE_W / 2, y - 26, url.replace("https://", ""))

    # How it works
    steps = [
        "Point your phone's camera at the code and open the link.",
        "Enter your email address and initials to log this park.",
        "Visit at least 20 of the 24 locations to earn your LLAMA Challenge patch!",
    ]
    y -= 0.68 * inch
    for i, text in enumerate(steps, 1):
        c.setFillColor(GREEN)
        c.circle(MARGIN + 0.55 * inch, y + 5, 11, stroke=0, fill=1)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 13)
        c.drawCentredString(MARGIN + 0.55 * inch, y + 0.5, str(i))
        c.setFillColor(DARK)
        c.setFont("Helvetica", 15)
        c.drawString(MARGIN + 0.85 * inch, y, text)
        y -= 0.34 * inch
    c.setFillColor(MUTED)
    c.setFont("Helvetica", 11.5)
    c.drawCentredString(PAGE_W / 2, y - 2,
                        "See the leaderboard and all 24 locations at www.llamasystem.org/challenge.html")
    footer(c)
    c.showPage()


def checklist_page(c):
    top = header(c, "Parks, trails and preserves checklist")
    width = PAGE_W - 2 * MARGIN
    y = top - 0.55 * inch
    c.setFillColor(DARK)
    c.setFont("Helvetica-Bold", 17)
    c.drawCentredString(PAGE_W / 2, y, "Visit at least 20 of these 24 locations to earn a LLAMA Challenge patch!")
    y -= 0.32 * inch
    c.setFont("Helvetica", 13)
    c.setFillColor(MUTED)
    c.drawCentredString(PAGE_W / 2, y,
                        "Scan the QR code on the flyer at each location to log your visit. No deadline!")

    # Two columns of 12
    y -= 0.5 * inch
    col_w = width / 2
    row_h = 0.37 * inch
    for i, (key, name, _) in enumerate(PARKS):
        name = SHORT.get(key, name)
        col, row = divmod(i, 12)
        x = MARGIN + col * col_w + 0.1 * inch
        yy = y - row * row_h
        c.setStrokeColor(GREEN)
        c.setLineWidth(1.5)
        c.roundRect(x, yy - 3, 14, 14, 3, stroke=1, fill=0)
        c.setFillColor(DARK)
        size = fit_font(c, name, "Helvetica", 12.5, col_w - 0.5 * inch, min_size=9)
        c.setFont("Helvetica", size)
        c.drawString(x + 22, yy, name)

    # QR to the challenge page
    url = SITE + "/challenge.html"
    qr_size = 2.1 * inch
    y = y - 12 * row_h - 0.25 * inch
    text_x = MARGIN + 0.1 * inch
    c.setFillColor(DARK)
    c.setFont("Helvetica-Bold", 15)
    c.drawString(text_x, y - 0.3 * inch, "Track your progress online")
    c.setFont("Helvetica", 12.5)
    c.setFillColor(MUTED)
    lines = wrap(c, "Scan this code for directions to every location, the leaderboard, "
                    "and how to request your patch ($5, covers shipping).",
                 "Helvetica", 12.5, width - qr_size - 0.6 * inch)
    ty = y - 0.58 * inch
    for line in lines:
        c.drawString(text_x, ty, line)
        ty -= 17
    c.setFillColor(GREEN)
    c.setFont("Helvetica-Bold", 12.5)
    c.drawString(text_x, ty - 4, "www.llamasystem.org/challenge.html")
    c.drawImage(qr_image(url), PAGE_W - MARGIN - qr_size, y - qr_size, qr_size, qr_size)
    footer(c)
    c.showPage()


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    out = Path(sys.argv[1])
    out.mkdir(parents=True, exist_ok=True)
    only = set(sys.argv[2:])
    unknown = only - {k for k, _, _ in PARKS}
    if unknown:
        sys.exit("Unknown park keys: " + ", ".join(sorted(unknown)))

    c = canvas.Canvas(str(out / "park-flyers.pdf"), pagesize=letter)
    c.setTitle("LLAMA Challenge park check-in flyers")
    for n, (key, name, town) in enumerate(PARKS, 1):
        if not only or key in only:
            park_page(c, key, name, town, n)
    c.save()

    c = canvas.Canvas(str(out / "checklist-flyer.pdf"), pagesize=letter)
    c.setTitle("LLAMA Challenge checklist")
    checklist_page(c)
    c.save()
    print("Wrote", out / "park-flyers.pdf", "and", out / "checklist-flyer.pdf")


if __name__ == "__main__":
    main()
