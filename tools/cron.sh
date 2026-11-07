#!/bin/sh
# Runs every 10 minutes from cron (installed by tools/setup.sh):
#   1. If GitHub has new commits on main, pull them and publish site/ to the web root.
#   2. Rebuild the public leaderboard and email notices for new finishers.
# Prints only when something happens, so the log stays small.
#
# Settings (environment, all optional):
#   LLAMA_PHP      PHP command            (default: php)
#   LLAMA_WEB      web root               (default: ~/llamasystem.org)
#   LLAMA_NOTIFY   finisher notice email  (default: none)
set -u
PATH=/usr/local/bin:/usr/bin:/bin:$PATH

REPO=$(cd "$(dirname "$0")/.." && pwd)
WEB=${LLAMA_WEB:-$HOME/llamasystem.org}
PHP=${LLAMA_PHP:-php}
LOCK=/tmp/llama-cron-$(id -u).lock

# Skip this run if the previous one is still going.
if ! mkdir "$LOCK" 2>/dev/null; then
    exit 0
fi
trap 'rmdir "$LOCK"' EXIT

cd "$REPO" || exit 1

# 1. Publish new commits. rsync never deletes, so /mdr/ and other files in
#    the web root are left alone.
if git fetch -q origin main; then
    if [ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ]; then
        if git merge -q --ff-only origin/main; then
            rsync -a "$REPO/site/" "$WEB/"
            echo "$(date '+%F %T') published $(git log -1 --format='%h %s')"
        else
            echo "$(date '+%F %T') could not update the server copy of the repository; resolve by hand in $REPO"
        fi
    fi
else
    echo "$(date '+%F %T') git fetch failed"
fi

# 2. Leaderboard and finisher notices.
if [ -n "${LLAMA_NOTIFY:-}" ]; then
    "$PHP" "$REPO/tools/build_leaderboard.php" "$WEB/checkin/leaderboard.json" --notify "$LLAMA_NOTIFY"
else
    "$PHP" "$REPO/tools/build_leaderboard.php" "$WEB/checkin/leaderboard.json"
fi
