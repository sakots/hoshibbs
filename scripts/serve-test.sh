#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

port=${1:-8080}
if [[ ! $port =~ ^[1-9][0-9]{0,4}$ ]] || ((port > 65535)); then
  printf '%s\n' 'ポートは 1〜65535 の整数で指定してください。' >&2
  exit 1
fi

if [[ ! -f hoshibbs/vendor/autoload.php ]]; then
  printf '%s\n' '依存ライブラリがありません。composer install --working-dir=hoshibbs を実行してください。' >&2
  exit 1
fi

workspace=$(mktemp -d)
pid=
cleanup() {
  if [[ -n $pid ]]; then
    kill "$pid" 2>/dev/null || true
    wait "$pid" 2>/dev/null || true
  fi
  rm -r -- "$workspace"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

web="$workspace/hoshibbs"
mkdir "$web" "$workspace/sessions"
cp hoshibbs/{index.php,config.php,read-compat.php} "$web/"
cp -r hoshibbs/templates "$web/"
ln -s "$PWD/hoshibbs/vendor" "$web/vendor"
cat > "$web/config.local.php" <<PHP
<?php
return [
  'boards' => ['general' => '雑談', 'help' => '質問'],
  'public_url' => 'http://127.0.0.1:$port/',
];
PHP

php -d session.save_path="$workspace/sessions" -S "127.0.0.1:$port" -t "$web" "$web/index.php" &
pid=$!
wait "$pid"
