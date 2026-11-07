# QR check-in (PHP)

Replaces the five retired Node servers in `legacy/node-servers/`. Nothing runs continuously. DreamHost's web server runs a PHP script only when someone submits a check-in, and cron rebuilds the public leaderboard on a schedule.

## How it works

| Piece | Where | What it does |
|---|---|---|
| `site/checkin/submit.php` | `https://www.llamasystem.org/checkin/submit.php` | Receives a check-in from the park popup and appends it to the private data file |
| `site/checkin/lib.php` | (not web-accessible) | Park list, data location, reading and writing check-ins, leaderboard logic |
| `site/checkin/admin/report.php` | `https://www.llamasystem.org/checkin/admin/report.php` | Password-protected report of every participant, with a CSV download |
| `tools/build_leaderboard.php` | run by cron | Writes `checkin/leaderboard.json` (initials and park names only) |
| `site/scripts/leaderboard.js` | on `challenge.html` | Shows the leaderboard from `leaderboard.json` |
| `tools/import_legacy.php` | run once | Copies the old Node servers' `database.json` check-ins into the new data file |

**Flow:**
1. A visitor scans a QR code, which opens `llamasystem.org/?place=<park>`. Scanning a code for the Ultra challenge opens `llamasystem.org/?ultra=<park>`.
2. The popup form posts to `/checkin/submit.php`.
3. The script saves the check-in to `~/llama-data/checkins.jsonl`, one JSON record per line, and the visitor sees a confirmation.

**Where the data lives:** check-ins go in `~/llama-data/`, outside the public website, so participants' emails are never web-accessible. To use another folder, set the `LLAMA_DATA_DIR` environment variable.

**Public leaderboard:** it lists initials, how many of the 24 challenge parks each person has visited, which parks, and their last check-in date. Emails appear only on the password-protected report.

**Finishers:** these are still confirmed by a person. The report highlights anyone with 20 or more parks, and then someone adds them to the Finishers table on `challenge.html`.

## One-time server setup

Run these on DreamHost (`ssh into the DreamHost account (login in 1Password)`).

### 1. Get the code

The repository is private, so the server needs a read-only deploy key, the same way the MDR repositories work:

```sh
ssh-keygen -t ed25519 -f ~/.ssh/llama_deploy -N "" -C "llamasystem deploy (DreamHost)"
cat ~/.ssh/llama_deploy.pub
```

Add that public key on GitHub: shenfll/Llamasystem → Settings → Deploy keys → Add deploy key. Leave "Allow write access" unchecked. Then:

```sh
GIT_SSH_COMMAND="ssh -i ~/.ssh/llama_deploy -o IdentitiesOnly=yes" \
  git clone git@github.com:shenfll/Llamasystem.git ~/llamasystem-repo
cd ~/llamasystem-repo
git config core.sshCommand "ssh -i ~/.ssh/llama_deploy -o IdentitiesOnly=yes"
```

### 2. Check PHP

```sh
php -v
for f in site/checkin/*.php site/checkin/admin/*.php tools/*.php; do php -l "$f"; done
```

### 3. Back up and import the old check-ins

```sh
mkdir -p ~/llama-data && chmod 700 ~/llama-data
mkdir -p ~/llama-data/legacy-backup
cp -p ~/database-server/database.json ~/llama-data/legacy-backup/challenge-database.json
cp -p ~/ultra-database-server/database.json ~/llama-data/legacy-backup/ultra-database.json
php ~/llamasystem-repo/tools/import_legacy.php \
  ~/llama-data/legacy-backup/challenge-database.json \
  ~/llama-data/legacy-backup/ultra-database.json
```

### 4. Set the report password

```sh
htpasswd -c ~/.htpasswd-llama <username>      # prompts for a password; store it in 1Password
chmod 644 ~/.htpasswd-llama
```

Add more people later with `htpasswd ~/.htpasswd-llama <name>`, without `-c`. `site/checkin/admin/.htaccess` expects this file at `/home/dh_6n4gzz/.htpasswd-llama`.

### 5. Deploy the site files

This copies the repository's `site/` folder into the web root. It adds and replaces files but **never deletes anything**, so `/mdr/` and other files on the server are untouched:

```sh
rsync -av ~/llamasystem-repo/site/ ~/llamasystem.org/
```

Never add `--delete` to that command.

### 6. Build the leaderboard and schedule it

```sh
php ~/llamasystem-repo/tools/build_leaderboard.php ~/llamasystem.org/checkin/leaderboard.json
```

Add this line with `crontab -e`, **below** the `###--- END DREAMHOST BLOCK` line. It rebuilds the leaderboard every day at 3:15 am:

```cron
15 3 * * * /usr/bin/env php /home/dh_6n4gzz/llamasystem-repo/tools/build_leaderboard.php /home/dh_6n4gzz/llamasystem.org/checkin/leaderboard.json >> /home/dh_6n4gzz/llama-data/leaderboard.log 2>&1
```

If `php` isn't found under cron, replace `/usr/bin/env php` with the full path from `which php`.

### 7. Test

```sh
# Should return {"ok":true,...}
curl -s -X POST https://www.llamasystem.org/checkin/submit.php \
  -d mode=challenge -d park=kinns -d email=test@example.com -d initials=TEST
tail -1 ~/llama-data/checkins.jsonl

# Should be refused (403) without the password
curl -s -o /dev/null -w "%{http_code}\n" https://www.llamasystem.org/checkin/lib.php
curl -s -o /dev/null -w "%{http_code}\n" https://www.llamasystem.org/checkin/admin/report.php
```

Then open the report in a browser, log in, and check the test entry. Afterwards, delete the test line from `~/llama-data/checkins.jsonl` (for example with `nano`).

## Updating the site later

```sh
cd ~/llamasystem-repo && git pull && rsync -av site/ ~/llamasystem.org/
```

## Troubleshooting

- **The report says "Password protection is not active".** The web server didn't pass the logged-in user to PHP. Check that `~/.htpasswd-llama` exists and that the path in `site/checkin/admin/.htaccess` matches.
- **A check-in says it could not be saved.** PHP couldn't write to `~/llama-data`. Check the folder's permissions (`ls -ld ~/llama-data`).
- **The leaderboard says "not available".** `leaderboard.json` hasn't been built yet, or the cron job is failing. Look in `~/llama-data/leaderboard.log`.
