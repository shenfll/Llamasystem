#!/bin/sh
# One-time setup of the LLAMA check-in on DreamHost. Safe to run again.
#
#   sh ~/llamasystem-repo/tools/setup.sh
#
# It will:
#   1. check PHP and the PHP files
#   2. back up the old Node servers' check-in data and import it (once)
#   3. ask for a password for the private report page (first time only)
#   4. publish site/ to the web root (adds and replaces files, never deletes)
#   5. build the leaderboard
#   6. add the 10-minute cron job (if it isn't there already)
#
# Settings (environment, all optional):
#   LLAMA_WEB      web root               (default: ~/llamasystem.org)
#   LLAMA_NOTIFY   finisher notice email  (default: llamasystem@gmail.com)
#   LLAMA_SKIP_LEGACY=1   do not import the old check-ins
set -eu

REPO=$(cd "$(dirname "$0")/.." && pwd)
WEB=${LLAMA_WEB:-$HOME/llamasystem.org}
DATA=${LLAMA_DATA_DIR:-$HOME/llama-data}
NOTIFY=${LLAMA_NOTIFY:-llamasystem@gmail.com}
HTPASSWD_FILE=$HOME/.htpasswd-llama

step() { printf '\n== %s\n' "$1"; }

step "1. Checking PHP"
PHP=$(command -v php || true)
if [ -z "$PHP" ]; then
    echo "php was not found. Ask DreamHost support which PHP command to use."
    exit 1
fi
"$PHP" -v | head -1
for f in "$REPO"/site/checkin/*.php "$REPO"/site/checkin/admin/*.php "$REPO"/tools/*.php; do
    "$PHP" -l "$f" >/dev/null
done
echo "All PHP files OK."

step "2. Old check-ins"
mkdir -p "$DATA/legacy-backup"
chmod 700 "$DATA"
if [ -f "$DATA/checkins.jsonl" ] && grep -q '"src":"legacy"' "$DATA/checkins.jsonl"; then
    echo "Already imported; skipping."
elif [ "${LLAMA_SKIP_LEGACY:-}" = "1" ]; then
    echo "Skipping import (LLAMA_SKIP_LEGACY=1)."
elif [ -f "$HOME/database-server/database.json" ]; then
    cp -p "$HOME/database-server/database.json" "$DATA/legacy-backup/challenge-database.json"
    if [ -f "$HOME/ultra-database-server/database.json" ]; then
        cp -p "$HOME/ultra-database-server/database.json" "$DATA/legacy-backup/ultra-database.json"
        "$PHP" "$REPO/tools/import_legacy.php" "$DATA/legacy-backup/challenge-database.json" "$DATA/legacy-backup/ultra-database.json"
    else
        "$PHP" "$REPO/tools/import_legacy.php" "$DATA/legacy-backup/challenge-database.json"
    fi
    echo "Backups of the old files are in $DATA/legacy-backup/"
else
    echo "No old database found; nothing to import."
fi

step "3. Report page password"
if [ -f "$HTPASSWD_FILE" ]; then
    echo "$HTPASSWD_FILE already exists; keeping it."
else
    if ! command -v htpasswd >/dev/null; then
        echo "htpasswd was not found. Create $HTPASSWD_FILE in the DreamHost panel instead, then run this again."
        exit 1
    fi
    printf 'Username for the report page: '
    read -r REPORT_USER
    htpasswd -c "$HTPASSWD_FILE" "$REPORT_USER"
    chmod 644 "$HTPASSWD_FILE"
    echo "Save this username and password in 1Password."
fi

step "4. Publishing site files to $WEB"
rsync -a "$REPO/site/" "$WEB/"
echo "Done (nothing was deleted; /mdr/ is untouched)."

step "5. Building the leaderboard"
"$PHP" "$REPO/tools/build_leaderboard.php" "$WEB/checkin/leaderboard.json" --notify "$NOTIFY"
ls -l "$WEB/checkin/leaderboard.json"

step "6. Cron job"
LINE="*/10 * * * * LLAMA_PHP=$PHP LLAMA_NOTIFY=$NOTIFY /bin/sh $REPO/tools/cron.sh >> $DATA/cron.log 2>&1"
if crontab -l 2>/dev/null | grep -q 'tools/cron.sh'; then
    echo "Already installed:"
    crontab -l | grep 'tools/cron.sh'
else
    (crontab -l 2>/dev/null; echo "$LINE") | crontab -
    echo "Added:"
    echo "$LINE"
fi

step "Finished"
echo "Check-in:    https://www.llamasystem.org/checkin/submit.php"
echo "Report:      https://www.llamasystem.org/checkin/admin/report.php"
echo "Leaderboard: https://www.llamasystem.org/challenge.html"
echo "Log:         $DATA/cron.log"
