# The LLAMA System

Source for [llamasystem.org](https://www.llamasystem.org/). LLAMA stands for Local Links that Augment and Motivate Activity.

The Shenendehowa Robotic Renovators, a FIRST Lego League team, built this site in 2020–21. It maps parks, trails and nature preserves in the Shenendehowa Central School District (Saratoga County, NY). It also runs the LLAMA Challenge: visit at least 20 of the 24 locations to earn a patch.

## Layout

| Path | What it is |
|---|---|
| `site/` | The public website: static HTML, CSS, JavaScript and images, deployed to the web root on DreamHost |
| `site/scripts/data.js` | Park locations, trails and popup text |
| `site/scripts/script.js` | Map logic, TEA LLAMA connections, QR check-in popup |
| `site/scripts/plot.js` | Mapbox map setup |
| `legacy/node-servers/` | The original Node.js check-in, report and hit-counter servers (retired, kept for reference) |

The first commit is the February 2025 version of the site, taken from the Replit export `ShenFLL (66).zip` (March 2025). That version was uploaded to `/ShenFLL/` on the server but never reached the root of the live site.

## Hosting

The site is hosted on a DreamHost shared plan. That plan serves static files and PHP, and runs cron jobs at most once a minute. It does not keep long-running programs alive. The same web root also hosts the separate Mountain Dog Running site under `/mdr/`. Deploys must only touch files owned by this project and must never remove or overwrite `/mdr/`.

## Why the legacy servers are retired

The original check-in used five Node servers on custom ports (2020, 3030, 4040, 5050, 7070). They were kept running inside `screen` sessions and used a hand-copied certificate. They stopped whenever the server restarted, and the certificate copy expired in May 2023. The replacement plan is a small PHP check-in script under `/checkin/`, plus a scheduled job that rebuilds the leaderboard.

## Privacy

Never commit check-in data, participant emails or addresses, certificates or passwords. `.gitignore` blocks the common cases. Check `git status` before every commit anyway.
