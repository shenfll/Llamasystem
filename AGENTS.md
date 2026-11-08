# Instructions for AI agents working on the LLAMA System

Read this whole file before changing anything. It explains what the project is, the limits of the server it runs on, the rules that protect participants' privacy, and how changes are tested and published.

## What this project is

The LLAMA System ("Local Links that Augment and Motivate Activity") is [llamasystem.org](https://www.llamasystem.org/). The Shenendehowa Robotic Renovators, a middle-school FIRST LEGO League team, built it in 2020–21. Naomi Hoffman, the club advisor, owns the project; Bill Hoffman maintains the code and server.

It has three parts:

1. **Park map.** About 50 parks, trails and preserves in the Shenendehowa Central School District (Clifton Park, Halfmoon, Ballston Lake, Malta, Rexford and nearby, Saratoga County, NY), shown on a Mapbox map. "TEA LLAMA" draws the shortest connections between them.
2. **LLAMA Challenge.** Visitors visit at least 20 of 24 listed locations and earn a patch ($5, mailed). Each location has a 5 x 7 flyer with a QR code. Scanning it shows the park's info, with a **"Log my visit"** button that records a check-in.
3. **Invasive plant tracker (prototype).** A new idea: people photograph invasive plants and put them on a map. Currently a browser-only prototype at `/invasives/`. See "The invasive plant tracker" below.

The audience is families, kids and community members, many on phones in parks. Keep things simple, readable and fast on a phone.

## Repository layout

| Path | What it is |
|---|---|
| `site/` | Everything published to the website. Copied into the web root by `tools/deploy.sh` |
| `site/index.html`, `mobile.html`, `all.html`, `mobile-all.html` | Map pages. Phones are redirected to the `mobile` versions |
| `site/challenge.html` | Challenge rules, Finishers table, leaderboard, 24-location list, checklist download |
| `site/scripts/data.js` | All map locations (`mydata.points`), their popups (title, text, image) and connections |
| `site/scripts/script.js` | Graph building, popups, QR check-in flow (`addCheckinButton`, `checkinForm`, `sendCheckin`), `showPlace`, `INFO_FROM` |
| `site/scripts/plot.js` | Mapbox map setup and marker click handling |
| `site/scripts/leaderboard.js` | Fills the leaderboard and new finishers on `challenge.html` from `checkin/leaderboard.json` |
| `site/checkin/` | PHP check-in: `submit.php` (public), `lib.php` (shared logic, blocked from the web), `admin/report.php` (password protected) |
| `site/invasives/index.html` | Invasive plant tracker prototype, published but not linked from any page |
| `site/pics/`, `site/images/` | Photos and images. Photos the team took are preferred |
| `tools/` | Server scripts: `setup.sh` (one time), `deploy.sh` (publish), `cron.sh` (every 10 minutes), `build_leaderboard.php`, `import_legacy.php`, `make_flyers.py` |
| `flyers/` | Printable 5 x 7 PDFs: 24 park QR cards and the checklist |
| `docs/CHECKIN.md` | Check-in setup, operation and troubleshooting |
| `legacy/node-servers/` | The retired 2021 Node.js servers, for reference only. Do not revive them |

## Hosting limits (read before designing anything)

The site runs on a **DreamHost shared hosting plan**. That plan:

- serves static files and runs **PHP** per request (PHP 8.2 at last check);
- runs **cron** jobs, at most once a minute (the LLAMA job runs every 10 minutes);
- does **not** keep long-running programs alive, and custom ports are not usable.

So: **no Node/Python servers, no websockets, no listening on ports, no `screen` sessions.** The original check-in failed for exactly this reason (see `legacy/`). Anything dynamic must be a PHP script that runs when a request arrives, or a cron job that writes static files. Data goes in files in a private folder outside the web root (`~/llama-data/`), not a database server.

**The web root is shared with a separate project.** The Mountain Dog Running site lives under `/mdr/` in the same web root. Never delete, overwrite, redirect or add rules that could affect `/mdr/`. Do not add a `.htaccess` at the top of `site/`; rsync would replace any existing one on the server. Folder-level `.htaccess` files inside our own folders (like `site/checkin/`) are fine.

## Privacy and safety rules (non-negotiable)

- **Never commit participant data**: check-ins, email addresses, mailing addresses, the finisher letters, spreadsheets, or anything from `~/llama-data/`. `.gitignore` blocks common cases; check `git status` before every commit anyway.
- **The public site shows initials only.** Leaderboard, Finishers table and any future public list use initials. Exception: Naomi is shown as "Naomi", set in `people.json`. Do not add full names of participants.
- **Emails appear only on the password-protected report** (`/checkin/admin/report.php`). Never put emails in public JSON, HTML or logs.
- `~/llama-data/people.json` (server only, never in the repo) links a person's multiple email addresses, marks finishers verified by photo before the new check-in, and sets display names. See `docs/CHECKIN.md`.
- **No secrets in the repo**: no passwords, private keys, server hostnames or login names beyond what is already in `site/checkin/admin/.htaccess`. Credentials live in 1Password (the report login is the "llama report page" entry).
- Much of the audience is kids. Photos should not show people; ask for initials, not names; give safety warnings (for example giant hogweed burns skin).
- Do not copy images from other websites into the repo or hotlink them. Several popups broke because linked images were removed. Use the team's own photos, or freely licensed ones (Wikimedia Commons, public domain, Creative Commons) stored in `site/pics/` with a visible credit in the popup.

## How changes reach the website

1. Make the change in this repository and commit it to `main`.
2. Bill runs `sh ~/llamasystem-repo/tools/deploy.sh` on the server **on purpose** when he wants it live. Nothing publishes automatically. Do not add automatic pulling from GitHub.
3. `deploy.sh` pulls `main`, shows the commits being published, lint-checks the PHP, copies `site/` into the web root with `rsync` (**never with `--delete`**), stamps `?v=<commit>` onto script and stylesheet links in top-level pages so phones fetch new files, and rebuilds the leaderboard.

Agents cannot log in to the server. If something must be run there, write the exact commands for Bill and say what output to expect. Give commands that can be pasted in one go. If a command prompts for input, make it the last command.

Publishing only adds and replaces files. If you remove or rename a file under `site/`, tell Bill to delete the old copy on the server.

## Testing

- PHP is not installed on Bill's Mac. Do not claim PHP was run locally. Check PHP carefully, and rely on `php -l` and the tests in `docs/CHECKIN.md` on the server. If you can install PHP or use Docker in your environment, do test.
- JavaScript: `node --check file.js`. For logic, the map scripts can be loaded in Node with stubbed `window` and `document` (load `data.js`, then `script.js` up to the line `var modal = document`) to simulate marker clicks and graph building.
- Pages: headless Chrome can screenshot pages (`--headless=new --screenshot`) and print PDFs (`--print-to-pdf`). Headless Chrome will not make windows narrower than about 500px. Test phone layouts by loading the page in a 390px-wide iframe.
- Check that the live site and `/mdr/` still respond after a deploy: `curl -s -o /dev/null -w '%{http_code}' https://www.llamasystem.org/mdr/`.

## Conventions that must stay in sync

- **Park keys** (for example `kinns`, `towpathLock7Lookout`) are permanent. They are printed in the QR codes on flyers posted in parks (`?place=<key>`) and stored in check-in data. **Never rename a park key.** Add a new key instead.
- The **24 challenge locations** are listed in four places that must agree: `LLAMA_CHALLENGE_PARKS` in `site/checkin/lib.php`, `PARKS` in `tools/make_flyers.py`, the list in `site/challenge.html`, and `CHALLENGE` in any report tooling.
- `LLAMA_PARKS` in `lib.php` names every location that may be checked in. It must include every challenge key.
- Check-ins are one JSON object per line in `~/llama-data/checkins.jsonl`: `{"t": ISO time or null, "mode": "challenge"|"ultra", "park": key, "email", "initials", "src": "web"|"legacy"}`. Old records have hand-typed park names; `llama_normalize_park()` maps them to keys.
- Popups: a location without its own `popup` can borrow one through `INFO_FROM` in `script.js` (used for extra entrances). Photos that fail to load fall back to the LLAMA logo.
- The **check-in flow**: `?place=<key>` shows the park popup with a "Log my visit" button; `?ultra=<key>` does the same for the Ultra challenge; `?point=<key>` shows info only. Only flyer links should offer check-in.
- Flyers are regenerated with `tools/make_flyers.py` (5 x 7 inches). Commit regenerated PDFs to `flyers/` and copy the checklist to `site/images/llama-checklist.pdf`.

## Working with Bill and Naomi

- **Show a local mockup before changing how something looks or works** (open it in the browser or attach a screenshot), then make the change once it's approved.
- Explain things in plain language. Naomi is not a programmer; Bill is.
- When reporting results, say what was tested and what was not.
- Commit messages: a short summary line, a blank line, then why the change was made.

## The invasive plant tracker

**Goal (from Naomi):** an invasive plant tracker for the LLAMA area, similar in spirit to [NY iMapInvasives](https://www.nyimapinvasives.org/) but local, where kids and community members photograph invasive plants, describe them, and see each photo pinned to its location on a map. It should feel like part of the LLAMA System and help teach the team.

**Current prototype** (`site/invasives/index.html`, live at `/invasives/`, not linked, `noindex`):

- Leaflet map with Mapbox "outdoors" tiles (the same public key and style as the main map) and the LLAMA parks loaded from `../scripts/data.js`.
- Report flow: pick a spot on the map or use the phone's location, choose a plant, add a photo (resized in the browser to 900px JPEG), description, initials and date.
- Sightings list with a species filter. Plant guide with 13 common local invasives and a giant hogweed warning. "How it works" tab with a roadmap, safety rules and discussion questions.
- **Reports are stored only in the visitor's browser (`localStorage`).** Seven example sightings near real parks are clearly marked "Example". Do not present examples as real data.
- It is an original design. Do not copy iMapInvasives' branding, layout or text. Link to it as the official state system.

**A real version should:**

1. Store reports on the server with PHP, following the check-in pattern: `site/invasives/api/submit.php` saves a JSON line to a private file under `~/llama-data/invasives/`, and photos to a private folder, never directly into the public web root.
2. **Strip photo location metadata (EXIF)** before saving. Resizing in a canvas already drops it; keep doing that, and re-check server-side.
3. Have an adult **review every report** before it is public: a password-protected review page under `site/invasives/admin/` (like `checkin/admin/`) that approves or rejects reports.
4. Publish approved reports through a cron job that writes a public `sightings.json` and copies approved, resized photos to a public folder. Initials only; no emails.
5. Validate input: known species list, location within the LLAMA area (warn otherwise), photo size and type limits, and a spam trap field like `submit.php` uses.
6. Possibly connect to the LLAMA map (invasives near each park) and a challenge or badge, if Naomi wants that. Sharing good reports with NY iMapInvasives would need their process; it can't be automated without their approval.

Keep the prototype working while building the real version; ask Bill and Naomi before replacing it or linking it from the main site.

## Open items (as of November 2026)

- Real photos needed for Hayes Nature Park, Halfmoon Town Park, Harris Park, Burning Bush Park and Clifton Gardens; nine challenge parks still use photos linked from other sites.
- Eleven map locations have no description (for example Calico Colony Park, Spirit Park, Indian Mortars Trail).
- The Winter Challenge page duplicates the Challenge page; Naomi to decide its future.
- The repository may be made public; keep it free of private data so that stays possible.
