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

# Phones keep old copies of scripts and stylesheets. Give every script and
# stylesheet link in the published pages this commit's version, e.g.
# scripts/script.js?v=a51669c, so each deploy makes browsers fetch the new
# files. Only the copies in the web root are changed, never the repository.
VER=$(git rev-parse --short HEAD)
for page in "$REPO"/site/*.html; do
    perl -pi -e 's{((?:src|href)=["\x27])((?:scripts|styles)/[^"\x27?]+)(?:\?v=[^"\x27]*)?(["\x27])}{$1$2?v='"$VER"'$3}g' \
        "$WEB/$(basename "$page")"
done
echo "Stamped script and stylesheet links with version $VER"

"$PHP" "$REPO/tools/build_leaderboard.php" "$WEB/checkin/leaderboard.json"
echo "Published $(git log -1 --format='%h %s') to $WEB"
