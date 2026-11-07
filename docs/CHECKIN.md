# QR check-in (PHP)

Replaces the five retired Node servers in `legacy/node-servers/`. Nothing runs continuously. After a one-time setup, check-ins, the leaderboard and finishers take care of themselves. Publishing code changes is a deliberate manual step (`tools/deploy.sh`).

## What happens automatically

| When | What happens |
|---|---|
| A visitor scans a park's QR code and submits the form | `site/checkin/submit.php` saves the check-in to `~/llama-data/checkins.jsonl` (private, outside the website) and tells the visitor it worked |
| Every 10 minutes (`tools/cron.sh`) | The leaderboard is rebuilt (`tools/build_leaderboard.php` writes `checkin/leaderboard.json`) |
| Someone reaches 20 of the 24 challenge parks through check-ins | They appear on the Finishers list on `challenge.html`, and an email notice with their contact address goes to llamasystem@gmail.com so their patch can be sent |
| Someone visits 20 challenge parks in one day (Ultra check-ins) | Same, as an Ultra LLAMA Challenge finisher |

**What the public sees:**
- The leaderboard and the Finishers list show initials, park names and dates only.
- Emails appear only on the password-protected report at `/checkin/admin/report.php`, which also offers a CSV download.

**Report page login:** see the **"llama report page"** entry in 1Password.

**What stays manual:**
- Mailing the patch itself, and collecting the $5.
- Earlier finishers (2021–2025) were verified by hand and are written directly in `challenge.html`.

## Files

| File | Purpose |
|---|---|
| `site/checkin/submit.php` | Receives check-ins |
| `site/checkin/lib.php` | Park list, storage, leaderboard and finisher logic (blocked from the web) |
| `site/checkin/admin/report.php` | Private report (password protected by `admin/.htaccess`) |
| `site/scripts/leaderboard.js` | Shows the leaderboard and new finishers on `challenge.html` |
| `tools/setup.sh` | One-time server setup |
| `tools/cron.sh` | The 10-minute job (leaderboard and finisher notices only) |
| `tools/deploy.sh` | Publishes the latest code from GitHub, run by hand |
| `tools/build_leaderboard.php` | Builds `leaderboard.json` and sends finisher notices |
| `tools/import_legacy.php` | Imports the old Node servers' check-ins (run by `setup.sh`) |

## One-time setup on DreamHost

Log in to the DreamHost account with SSH. The server name, username and password are in 1Password.

### 1. Give the server read-only access to the repository

```sh
ssh-keygen -t ed25519 -f ~/.ssh/llama_deploy -N "" -C "llamasystem deploy (DreamHost)"
cat ~/.ssh/llama_deploy.pub
```

On GitHub, go to shenfll/Llamasystem → Settings → Deploy keys → Add deploy key. Paste the key and leave "Allow write access" **unchecked**.

### 2. Clone and run the setup script

```sh
GIT_SSH_COMMAND="ssh -i ~/.ssh/llama_deploy -o IdentitiesOnly=yes" \
  git clone git@github.com:shenfll/Llamasystem.git ~/llamasystem-repo
cd ~/llamasystem-repo
git config core.sshCommand "ssh -i ~/.ssh/llama_deploy -o IdentitiesOnly=yes"
sh tools/setup.sh
```

**The setup script:**
1. Checks PHP.
2. Backs up and imports the old check-ins.
3. Asks once for a report username and password. Save these in 1Password as the **"llama report page"** entry.
4. Publishes the site.
5. Builds the leaderboard.
6. Adds the 10-minute cron job below the DreamHost block.

It's safe to run again.

**Options:**
- To start the leaderboard fresh instead of importing old progress, run `LLAMA_SKIP_LEGACY=1 sh tools/setup.sh`.
- To send finisher notices somewhere else, run `LLAMA_NOTIFY=someone@example.com sh tools/setup.sh`.

### 3. Test

```sh
# Should print {"ok":true,...}
curl -s -X POST https://www.llamasystem.org/checkin/submit.php \
  -d mode=challenge -d park=kinns -d email=test@example.com -d initials=TEST

# Both should print 403 (blocked without the password)
curl -s -o /dev/null -w "%{http_code}\n" https://www.llamasystem.org/checkin/lib.php
curl -s -o /dev/null -w "%{http_code}\n" https://www.llamasystem.org/checkin/admin/report.php
```

Open the report in a browser, log in, and check that the TEST entry is there. Then remove the test line with `nano ~/llama-data/checkins.jsonl`.

## Changing the site

1. Commit and push the change to `main` on GitHub.
2. When you want it live, run this on the server:

```sh
sh ~/llamasystem-repo/tools/deploy.sh
```

The script pulls from GitHub, lists the commits being published, checks the PHP files, and copies `site/` into the web root. Nothing is published until you run it.

**Two cautions:**
- **Publishing only adds and replaces files; it never deletes.** If you remove a file from `site/`, delete it on the server too.
- **Never add `--delete` to the rsync commands.** The same web root hosts the Mountain Dog Running site under `/mdr/`.

## Troubleshooting

| Symptom | Fix |
|---|---|
| The report says "Password protection is not active" | Check that `~/.htpasswd-llama` exists and matches the path in `site/checkin/admin/.htaccess` |
| A check-in says it could not be saved | PHP can't write to `~/llama-data`; check `ls -ld ~/llama-data` |
| The leaderboard says "not available" | Look in `~/llama-data/cron.log`, and run `sh ~/llamasystem-repo/tools/cron.sh` by hand to see errors |
| `deploy.sh` stops at `git pull` | Check the deploy key (step 1). If it says local changes would be overwritten, someone edited files in `~/llamasystem-repo` directly; make edits on GitHub instead |
| No finisher emails arrive | Check the llamasystem@gmail.com spam folder. Errors are logged in `~/llama-data/cron.log`, and failed notices are retried every 10 minutes |
