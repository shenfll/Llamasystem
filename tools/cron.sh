#!/bin/sh
# Runs every 10 minutes from cron (installed by tools/setup.sh).
# Rebuilds the public leaderboard and emails notices for new finishers.
# It does NOT pull code from GitHub; publishing is done on purpose with
# tools/deploy.sh. Prints only when something happens, so the log stays small.
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

if [ -n "${LLAMA_NOTIFY:-}" ]; then
    "$PHP" "$REPO/tools/build_leaderboard.php" "$WEB/checkin/leaderboard.json" --notify "$LLAMA_NOTIFY"
else
    "$PHP" "$REPO/tools/build_leaderboard.php" "$WEB/checkin/leaderboard.json"
fi
