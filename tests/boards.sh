#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
tmp=$(mktemp -d)
pid=
trap 'if [[ -n $pid ]]; then kill "$pid" 2>/dev/null || true; fi; rm -r -- "$tmp"' EXIT
mkdir "$tmp/hoshibbs"
cp hoshibbs/{index.php,config.php} "$tmp/hoshibbs/"
cp -r hoshibbs/templates "$tmp/hoshibbs/"
ln -s "$PWD/hoshibbs/vendor" "$tmp/hoshibbs/vendor"
cat > "$tmp/hoshibbs/config.local.php" <<'PHP'
<?php
return ['boards' => ['general' => '雑談', 'help' => '質問']];
PHP
port=${HOSHIBBS_TEST_PORT:-18782}
php -d session.save_path="$tmp" -S "127.0.0.1:$port" -t "$tmp/hoshibbs" > "$tmp/server.log" 2>&1 &
pid=$!
url="http://127.0.0.1:$port/index.php"
for _ in {1..50}; do
    kill -0 "$pid"
    if curl -fsS "$url" > "$tmp/home" 2>/dev/null; then break; fi
    sleep 0.1
done
grep -F '質問' "$tmp/home"
board="$url?board=general"
thread="$board&thread=1"
page=$(curl -fsS -c "$tmp/cookies" "$board")
csrf=$(printf '%s\n' "$page" | sed -n 's/.*name="csrf" value="\([0-9a-f]*\)".*/\1/p')
[[ ${#csrf} == 64 ]]
request() { curl -sS -b "$tmp/cookies" -o "$tmp/response" -w '%{http_code}' "$@"; }
[[ $(request -d 'title=Rejected&body=Rejected' "$board") == 403 ]]
[[ $(request -d "csrf=$csrf&title=Test+thread" --data-urlencode 'body=<b>First post</b>' "$board") == 303 ]]
[[ $(request "$thread") == 200 ]]
grep -F '&lt;b&gt;First post&lt;/b&gt;' "$tmp/response"
[[ $(request -d "csrf=$csrf&body=An+answer" "$thread") == 303 ]]
[[ $(request "$thread") == 200 ]]
grep -F 'An answer' "$tmp/response"
[[ $(request "$url?board=help") == 200 ]]
[[ $(<"$tmp/response") != *'Test thread'* ]]
[[ $(request "$url?board=help&thread=1") == 404 ]]
[[ $(request -d "csrf=$csrf&body=Wrong+board" "$url?board=help&thread=1") == 404 ]]
[[ $(request "$thread") == 200 ]]
[[ $(<"$tmp/response") != *'Wrong board'* && $(<"$tmp/response") != *'Rejected'* ]]
