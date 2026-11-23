#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

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
cp hoshibbs/{index.php,config.php} "$web/"
cp -r hoshibbs/templates "$web/"
ln -s "$PWD/hoshibbs/vendor" "$web/vendor"
cat > "$web/config.local.php" <<'PHP'
<?php
return ['boards' => ['general' => '雑談', 'help' => '質問']];
PHP

php -d session.save_path="$workspace/sessions" -S "127.0.0.1:${1:-8080}" -t "$web" &
pid=$!
wait "$pid"
