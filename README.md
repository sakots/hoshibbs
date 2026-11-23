# hoshibbs

SQLite を使う PHP 製の 2ch/5ch 型掲示板です。1 つの設置で複数の掲示板を運営できます。

## 設置

1. PHP 8.2 以上（PDO SQLite、セッション拡張が有効）と Composer を用意します。
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

## 更新履歴

### [2026/11/23] v0.1.0

- SQLite と Twig を使った複数掲示板の運営、スレッド作成、返信機能を追加。

### [2026/03/31] v0.0.0

- リポジトリを作成。
