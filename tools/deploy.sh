#!/bin/sh
# Publish the latest code from GitHub to the website. Run this on purpose
# when you want changes to go live:
#
#   sh ~/llamasystem-repo/tools/deploy.sh
#
# Pulls main, shows what changed, checks the PHP files, then copies site/
# into the web root. rsync never deletes, so /mdr/ and other files in the
# web root are left alone.
set -eu
PATH=/usr/local/bin:/usr/bin:/bin:$PATH

REPO=$(cd "$(dirname "$0")/.." && pwd)
WEB=${LLAMA_WEB:-$HOME/llamasystem.org}
PHP=${LLAMA_PHP:-$(command -v php)}

cd "$REPO"
BEFORE=$(git rev-parse HEAD)
git pull --ff-only origin main
AFTER=$(git rev-parse HEAD)

if [ "$BEFORE" = "$AFTER" ]; then
    echo "Already up to date ($(git log -1 --format='%h %s'))."
else
    echo "Changes being published:"
    git log --oneline "$BEFORE..$AFTER"
fi

for f in site/checkin/*.php site/checkin/admin/*.php tools/*.php; do
    "$PHP" -l "$f" >/dev/null
done

rsync -av "$REPO/site/" "$WEB/"
"$PHP" "$REPO/tools/build_leaderboard.php" "$WEB/checkin/leaderboard.json"
echo "Published $(git log -1 --format='%h %s') to $WEB"
