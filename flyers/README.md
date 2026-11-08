# Printable flyers (5 x 7 inches)

| File | What it is |
|---|---|
| `park-flyers.pdf` | 24 cards, one per challenge location. Each QR code opens that park's page on llamasystem.org, where visitors can log their visit. Post each card at its park. |
| `checklist-flyer.pdf` | The 24-location checklist, with a QR code to the Challenge page. Also on the website as `site/images/llama-checklist.pdf`. |

Print at **actual size (100%)**, not "fit to page", on 5 x 7 card stock or photo paper. Protect cards posted outdoors with a sheet protector or lamination.

Before posting a new card, scan it with a phone to check that it opens the right park.

## Regenerating

The flyers are made by `tools/make_flyers.py`. Change it (park names, wording), then:

```sh
pip install qrcode==7.4.2 reportlab==4.2.5 pillow==10.4.0
python tools/make_flyers.py flyers
cp flyers/checklist-flyer.pdf site/images/llama-checklist.pdf
```

To print one park only: `python tools/make_flyers.py /tmp/one kinns`. The park keys are listed in the script.

If the checklist changes, also update the preview image `site/images/llama-checklist.png` (720 x 1008 pixels; on a Mac: `qlmanage -t -s 1008 -o /tmp flyers/checklist-flyer.pdf`).
