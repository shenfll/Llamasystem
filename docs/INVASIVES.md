# Invasive plant tracker submissions

The tracker accepts photo reports through `site/invasives/api/submit.php` and community mitigation tips through `site/invasives/api/submit_tip.php`.

## Data flow

1. The browser resizes the selected photo to a 900-pixel JPEG. The PHP endpoint validates its type, size, species, initials, date and broad LLAMA-area coordinates, then re-encodes it to remove image metadata.
2. The report and photo are stored outside the website in `~/llama-data/invasives/` with private permissions. The report is not public yet.
3. An adult signs into `/invasives/admin/review.php` using the existing LLAMA report-page credentials. Approved reports are queued in `approved.jsonl`; rejected report details are reduced to an ID and timestamp and the private photo is removed.
4. The existing 10-minute cron job runs `tools/build_invasives.php`. It writes the approved, initials-only public list to `/invasives/sightings.json` and copies approved photos to `/invasives/photos/reports/`.
5. The public tracker reads only that generated list. Pending and rejected reports are never copied to the website.

## Community mitigation tips

1. A visitor chooses a species in the Plant guide, writes a tip (up to 800 characters), and may add an HTTP(S) source link. The form does not ask for a name, email, or location.
2. Tips are saved privately in `~/llama-data/invasives/pending-tips.jsonl` and never shown before review.
3. An adult reviews tips alongside plant reports at `/invasives/admin/review.php`. Approved tips are stored in `approved-tips.jsonl`; rejected tips are not published.
4. The existing cron builder writes approved tips to `/invasives/tips.json`, which the Plant guide reads. Public tips appear under their species as Community tips with an optional source link.

Reviewers should reject advice that encourages touching hazardous plants, removal without land-manager permission, or unsupervised herbicide use. For giant hogweed, keep guidance focused on telling an adult and following official directions.

The server code creates private folders on the first report. PHP GD is used to decode and re-encode photos; DreamHost documents GD as activated by default. If the endpoint reports that photo saving is temporarily unavailable, check the domain's enabled PHP extensions.

## Review and privacy

- The review page and its photo endpoint are protected by the existing `~/.htpasswd-llama` credentials.
- Never place pending reports, tips, or pending photos in the repository or website root. The generated `sightings.json`, `tips.json`, and approved photos are public outputs written by the builder after review.
- Public data includes the plant, approximate map coordinates, date seen, description, initials and approved photo. It does not include email or IP address.
- The browser-only reports from the old prototype remain on that browser and are not automatically uploaded. Re-submit any report that should be reviewed on the server.
- The tracker remains unlinked from the main LLAMA pages. Do not add a main-site link unless Naomi and Bill request it.

## Local preview

The normal preview command serves static files only; it cannot execute PHP. On `localhost`, the page displays a notice that reports cannot be saved from that preview. Live submissions and review become available after the PHP files and `.htaccess` rules are deployed to DreamHost.
