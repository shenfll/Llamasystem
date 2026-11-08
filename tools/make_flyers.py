#!/usr/bin/env python3
"""Make printable LLAMA Challenge flyers (5 x 7 inch PDFs).

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

# 5 x 7 inch cards (portrait)
PAGE_W, PAGE_H = 5 * inch, 7 * inch
MARGIN = 0.3 * inch
HEADER_H = 0.95 * inch
FOOTER_H = 0.42 * inch

# Shorter names for the two-column checklist.
SHORT = {
    "ferry_road": "Vischer Ferry - Ferry Road",
    "whipple": "Vischer Ferry - Whipple Bridge",
    "clutes": "Vischer Ferry - Clute's Dry Dock",
    "hayes_moe": "Hayes Nature Park - Moe Road",
    "mohawk_landing": "Mohawk Landing Preserve",
    "longkill": "Jewett Park - Longkill Road",
    "zim_coons": "Zim Smith - Coons Crossing",
}


def qr_image(url):
    qr = qrcode.QRCode(error_correction=ERROR_CORRECT_Q, box_size=20, border=2)
    qr.add_data(url)
    qr.make(fit=True)
    buf = io.BytesIO()
    qr.make_image(fill_color="black", back_color="white").save(buf, format="PNG")
    buf.seek(0)
    return ImageReader(buf)


def fit_font(c, text, font, max_size, max_width, min_size=8):
    size = max_size
    while size > min_size and c.stringWidth(text, font, size) > max_width:
        size -= 0.5
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
    c.setFillColor(GREEN)
    c.rect(0, PAGE_H - HEADER_H, PAGE_W, HEADER_H, stroke=0, fill=1)
    logo = 0.72 * inch
    cx, cy = MARGIN + logo / 2, PAGE_H - HEADER_H / 2
    c.setFillColor(white)
    c.circle(cx, cy, logo / 2 + 2, stroke=0, fill=1)
    # The logo is a round badge on a square image: clip it to a circle.
    c.saveState()
    clip = c.beginPath()
    clip.circle(cx, cy, logo / 2 - 0.5)
    c.clipPath(clip, stroke=0, fill=0)
    c.drawImage(str(LOGO), MARGIN, cy - logo / 2, logo, logo, mask="auto")
    c.restoreState()
    x = MARGIN + logo + 0.15 * inch
    room = PAGE_W - x - MARGIN
    c.setFillColor(white)
    c.setFont("Helvetica-Bold", fit_font(c, "THE LLAMA CHALLENGE", "Helvetica-Bold", 21, room))
    c.drawString(x, PAGE_H - 0.47 * inch, "THE LLAMA CHALLENGE")
    c.setFont("Helvetica", fit_font(c, subtitle, "Helvetica", 9.5, room))
    c.drawString(x, PAGE_H - 0.70 * inch, subtitle)
    return PAGE_H - HEADER_H


def footer(c):
    c.setFillColor(GREEN)
    c.rect(0, 0, PAGE_W, FOOTER_H, stroke=0, fill=1)
    c.setFillColor(white)
    c.setFont("Helvetica", 6.8)
    c.drawCentredString(PAGE_W / 2, 0.25 * inch,
                        "Created by the Robotic Renovators, a Shenendehowa Middle School FIRST LEGO League team.")
    c.drawCentredString(PAGE_W / 2, 0.11 * inch,
                        "Questions: llamasystem@gmail.com   ·   www.llamasystem.org")


def park_page(c, key, name, town, number):
    top = header(c, "Location %d of %d" % (number, len(PARKS)))
    width = PAGE_W - 2 * MARGIN

    # Park name: one line, or two (broken at the dash when there is one)
    size = fit_font(c, name, "Helvetica-Bold", 22, width, min_size=17)
    if c.stringWidth(name, "Helvetica-Bold", size) > width:
        lines = name.split(" - ", 1) if " - " in name else wrap(c, name, "Helvetica-Bold", 20, width)
        size = min(fit_font(c, line, "Helvetica-Bold", 20, width, min_size=13) for line in lines)
    else:
        lines = [name]
    y = top - 0.38 * inch
    c.setFillColor(DARK)
    c.setFont("Helvetica-Bold", size)
    for line in lines[:2]:
        c.drawCentredString(PAGE_W / 2, y, line)
        y -= size * 1.12
    if town:
        c.setFillColor(MUTED)
        c.setFont("Helvetica", 10.5)
        c.drawCentredString(PAGE_W / 2, y + 3, town)
        y -= 14
    if len(lines) == 1:
        y -= 6  # keep the code in the same place on one- and two-line names

    # QR code
    url = "%s/?place=%s" % (SITE, key)
    qr_size = 2.55 * inch
    y -= 0.08 * inch
    c.setFillColor(GREEN)
    c.setFont("Helvetica-Bold", 14)
    c.drawCentredString(PAGE_W / 2, y, "Scan to log your visit")
    y -= 0.2 * inch + qr_size
    c.setStrokeColor(GREEN)
    c.setLineWidth(2)
    c.roundRect((PAGE_W - qr_size) / 2 - 5, y - 5, qr_size + 10, qr_size + 10, 7, stroke=1, fill=0)
    c.drawImage(qr_image(url), (PAGE_W - qr_size) / 2, y, qr_size, qr_size)
    c.setFillColor(MUTED)
    c.setFont("Helvetica", 7.5)
    c.drawCentredString(PAGE_W / 2, y - 16, url.replace("https://", ""))

    # How it works
    steps = [
        "Scan the code with your phone's camera to see this park.",
        "Tap \"Log my visit\" and enter your email and initials.",
        "Visit 20 of the 24 locations to earn a LLAMA patch!",
    ]
    y -= 0.48 * inch
    for i, text in enumerate(steps, 1):
        c.setFillColor(GREEN)
        c.circle(MARGIN + 0.12 * inch, y + 3, 6.5, stroke=0, fill=1)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 8)
        c.drawCentredString(MARGIN + 0.12 * inch, y + 0.3, str(i))
        c.setFillColor(DARK)
        c.setFont("Helvetica", fit_font(c, text, "Helvetica", 9.5, width - 0.3 * inch))
        c.drawString(MARGIN + 0.3 * inch, y, text)
        y -= 0.22 * inch
    footer(c)
    c.showPage()


def checklist_page(c):
    top = header(c, "Parks, trails and preserves checklist")
    width = PAGE_W - 2 * MARGIN
    y = top - 0.3 * inch
    c.setFillColor(DARK)
    line1 = "Visit 20 of these 24 locations to earn a patch!"
    c.setFont("Helvetica-Bold", fit_font(c, line1, "Helvetica-Bold", 12, width))
    c.drawCentredString(PAGE_W / 2, y, line1)
    y -= 0.2 * inch
    c.setFillColor(MUTED)
    line2 = "Scan the QR code on the flyer at each location to log your visit."
    c.setFont("Helvetica", fit_font(c, line2, "Helvetica", 8.5, width))
    c.drawCentredString(PAGE_W / 2, y, line2)

    # Two columns of 12
    y -= 0.3 * inch
    col_w = width / 2
    row_h = 0.215 * inch
    for i, (key, name, _) in enumerate(PARKS):
        name = SHORT.get(key, name)
        col, row = divmod(i, 12)
        x = MARGIN + col * col_w
        yy = y - row * row_h
        c.setStrokeColor(GREEN)
        c.setLineWidth(1)
        c.roundRect(x, yy - 2, 8.5, 8.5, 2, stroke=1, fill=0)
        c.setFillColor(DARK)
        c.setFont("Helvetica", fit_font(c, name, "Helvetica", 8, col_w - 0.2 * inch, min_size=6.5))
        c.drawString(x + 13, yy, name)

    # QR to the challenge page
    url = SITE + "/challenge.html"
    qr_size = 1.3 * inch
    qy = FOOTER_H + 0.18 * inch
    c.drawImage(qr_image(url), PAGE_W - MARGIN - qr_size, qy, qr_size, qr_size)
    text_w = width - qr_size - 0.15 * inch
    ty = qy + qr_size - 12
    c.setFillColor(DARK)
    c.setFont("Helvetica-Bold", 10.5)
    c.drawString(MARGIN, ty, "Track your progress online")
    c.setFillColor(MUTED)
    c.setFont("Helvetica", 8)
    ty -= 13
    for line in wrap(c, "Scan for directions to every location, the leaderboard, and how to request "
                        "your patch ($5, covers shipping). No deadline!", "Helvetica", 8, text_w):
        c.drawString(MARGIN, ty, line)
        ty -= 10.5
    c.setFillColor(GREEN)
    c.setFont("Helvetica-Bold", fit_font(c, "www.llamasystem.org/challenge.html", "Helvetica-Bold", 8.5, text_w))
    c.drawString(MARGIN, ty - 3, "www.llamasystem.org/challenge.html")
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

    c = canvas.Canvas(str(out / "park-flyers.pdf"), pagesize=(PAGE_W, PAGE_H))
    c.setTitle("LLAMA Challenge park check-in flyers")
    for n, (key, name, town) in enumerate(PARKS, 1):
        if not only or key in only:
            park_page(c, key, name, town, n)
    c.save()

    c = canvas.Canvas(str(out / "checklist-flyer.pdf"), pagesize=(PAGE_W, PAGE_H))
    c.setTitle("LLAMA Challenge checklist")
    checklist_page(c)
    c.save()
    print("Wrote", out / "park-flyers.pdf", "and", out / "checklist-flyer.pdf")


if __name__ == "__main__":
    main()
