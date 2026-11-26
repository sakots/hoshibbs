#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
tmp=$(mktemp -d)
pid=
cleanup() {
  if [[ -n $pid ]]; then
    kill "$pid" 2>/dev/null || true
    wait "$pid" || true
  fi
  rm -r -- "$tmp"
}
trap cleanup EXIT
port=${HOSHIBBS_TEST_PORT:-18782}
TMPDIR="$tmp" bash scripts/serve-test.sh "$port" > "$tmp/server.log" 2>&1 &
pid=$!
url="http://127.0.0.1:$port/index.php"
for _ in {1..50}; do
  if ! kill -0 "$pid"; then
    cat "$tmp/server.log" >&2
    exit 1
  fi
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

base=${url%/index.php}
curl -fsS "$base/bbsmenu.json" | php -r '
  $menu = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
  $category = $menu["menu_list"][0];
  exit($category["category_total"] === 2
    && $category["category_content"][1]["url"] === $argv[1] . "/help/" ? 0 : 1);
' "$base"
curl -fsS "$base/bbsmenu.html" | iconv -f CP932 -t UTF-8 | grep -F "href=\"$base/help/\">質問</a>"
curl -fsSL "$base/general/" | grep -F 'Test thread'
[[ $(request -d "csrf=$csrf&body=Do+not+write" "$base/general/") == 405 ]]
[[ $(request -d "csrf=$csrf&title=Second&body=Another" "$board") == 303 ]]
curl -fsS "$base/general/subject.txt" > "$tmp/subject"
key=$(sed -n 's/^\([0-9]\{10\}\)\.dat<>Test thread (2)$/\1/p' "$tmp/subject")
second=$(sed -n 's/^\([0-9]\{10\}\)\.dat<>Second (1)$/\1/p' "$tmp/subject")
[[ -n $key && -n $second && $key != "$second" ]]
[[ -z $(curl -fsS "$base/help/subject.txt") ]]
[[ $(request "$base/help/dat/$key.dat") == 404 ]]
[[ $(request "$base/test/read.cgi/help/$key/") == 404 ]]
curl -fsSL "$base/test/read.cgi/general/$key/" | grep -F 'An answer'
dat="$base/general/dat/$key.dat"
curl -fsS "$dat" > "$tmp/dat"
[[ $(wc -l < "$tmp/dat") == 2 ]]
iconv -f CP932 -t UTF-8 "$tmp/dat" | grep -E '^名無しさん<><>[^<>]+<> &lt;b&gt;First post&lt;/b&gt; <>Test thread$'
[[ $(request -d "csrf=$csrf" --data-urlencode $'body=新着😀\r\n<>行' "$thread") == 303 ]]
curl -fsS "$dat" > "$tmp/updated"
cmp -n "$(wc -c < "$tmp/dat")" "$tmp/dat" "$tmp/updated"
iconv -f CP932 -t UTF-8 "$tmp/updated" | grep -Ei '新着&#(x1f600|128512); <br> &lt;&gt;行 <>$'
