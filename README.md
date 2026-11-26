# hoshibbs

SQLite を使う PHP 製の 2ch/5ch 型掲示板です。1 つの設置で複数の掲示板を運営できます。

## 設置

1. PHP 8.2 以上（PDO SQLite、セッション、mbstring 拡張が有効）と Composer を用意します。
2. `composer install --working-dir=hoshibbs --no-dev` を実行し、`vendor/` を含む `hoshibbs/` を公開します。
3. 掲示板名やデータベースの場所を変える場合は、`hoshibbs/config.local.php` を作り、変更する項目だけを返します。

   ```php
   <?php
   return [
     'boards' => ['general' => '雑談', 'help' => '質問'],
     'database' => '/var/lib/hoshibbs/boards.sqlite',
   ];
   ```

   掲示板 ID は英小文字・数字・ハイフンのみで、先頭は英小文字または数字です。既定では `general`（雑談）があり、SQLite は `hoshibbs/` の外側にある `hoshibbs-data/` に初回アクセス時に作られます。データベースとその親ディレクトリは Web で公開せず、PHP から書き込めるようにします。更新時は `config.local.php` とデータベースを上書きしないでください。

トップから掲示板を選ぶとスレッドの閲覧・作成ができ、スレッド内で返信できます。投稿は匿名のテキストのみです。

## 専用ブラウザ向けの閲覧

サイトのルートに設置し、互換 URL を `index.php` に内部転送してください。Apache では同梱の `.htaccess` を使えます（mod_rewrite と `AllowOverride FileInfo` が必要）。PHP の簡易サーバーは下記スクリプトが設定します。

外部板として `https://example.org/general/` を登録します。一括登録用のメニューを使うには、`config.local.php` に `'public_url' => 'https://example.org/'` を追加します。HTML メニューは `/bbsmenu.html`、JSON メニューは `/bbsmenu.json` です。専用ブラウザからの投稿には対応していません。詳しくは [互換設計](docs/design.md) を参照してください。

## ローカルテスト

リポジトリのルートで実行します（Bash、PHP 8.2 以上、Composer が必要です）。

```sh
composer install --working-dir=hoshibbs
bash scripts/serve-test.sh
```

ブラウザーで <http://127.0.0.1:8080/> を開きます。ポートを変えるには `bash scripts/serve-test.sh 8081` のように指定します。`Ctrl+C` で停止します。

テスト版は「雑談」と「質問」の2つの掲示板を用意します。設置者の `config.local.php` や実運用データは読み込まず、テスト投稿とセッションは一時ディレクトリに保存し、停止時に削除します。

## 更新履歴

### [2026/11/26] v0.2.0

- 専用ブラウザ向けの板メニュー、スレッド一覧、DAT 閲覧機能を追加。

### [2026/11/23] v0.1.0

- SQLite と Twig を使った複数掲示板の運営、スレッド作成、返信機能を追加。
- PHP の簡易サーバーでローカルテスト版を起動する機能を追加。

### [2026/03/31] v0.0.0

- リポジトリを作成。
