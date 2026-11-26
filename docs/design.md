# 5ch 専用ブラウザ向けの互換設計

hoshibbs を外部板として登録し、スレッド一覧と本文を読めるようにする。5ch 本体の API・認証・規制は再現しない。以下の読み取り URL を提供し、投稿には既存の Web フォームを使う。

最初の検証対象は、2ch 互換の外部板を明示的にサポートする [JDim](https://jdimproved.github.io/JDim/external/) とする。[ChMate の FAQ](https://chmate.airfront.co.jp/docs/faq/) は板 URL の指定と BBSMENU の取り込みを案内しているが、hoshibbs での動作は別に確認する。クライアント名・バージョンごとに確認できた操作だけを対応として案内する。

## 読み取り用 URL

例はサイト直下に設置した場合。`{board}` は表示名ではなく板 ID、`{key}` は公開スレッドキー。サブディレクトリ設置ではメニューのリンクに設置先のパスを含める。ただし、[JDim の外部板実装](https://github.com/JDimproved/JDim/blob/master/src/dbtree/board2chcompati.cpp) は `/test/read.cgi` を固定のパスとして設定しているため、まずサイト直下を検証し、サブディレクトリ設置はクライアント別に確認する。

| URL | 用途 | 出力 |
| --- | --- | --- |
| `/bbsmenu.html` | 外部板の一括登録 | HTML、CP932（`charset=Shift_JIS` と宣言） |
| `/bbsmenu.json` | JSON メニュー対応クライアント向け | UTF-8 JSON（`application/json`） |
| `/{board}/` | 外部板の登録先・Web 版の板 | 既存の板ページにつなぐ |
| `/{board}/subject.txt` | スレッド一覧 | CP932 の `text/plain`、LF 区切り |
| `/{board}/dat/{key}.dat` | スレッド本文の取得 | CP932 の `text/plain`、LF 区切り |
| `/test/read.cgi/{board}/{key}/` | スレッドの共有 URL | 既存の Web スレッド表示につなぐ。DAT の取得先とは別 |

板とスレッドは組で解決し、存在しない組は `404`。互換 URL は読み取り専用とし、投稿は現行の Web フォームと CSRF 検証を使う。専ブラからの投稿、過去ログ取得、5ch 固有の認証はこの設計の対象外。

一覧・DAT は全文を返す。条件付き GET やバイト範囲による差分配信は実装していない。

## 板一覧（BBSMENU）

[5ch の HTML メニュー](https://menu.5ch.io/bbsmenu.html) は HTTP ヘッダーと HTML 内の両方で `Shift_JIS` を宣言している。[Monazilla の板一覧資料](https://info.5ch.io/index.php/Monazilla/develop/boardlist) に沿って、`<B>カテゴリ名</B><BR>` に続けて、板ごとに `<A HREF="https://example.org/general/">雑談</A><BR>` を出力する。リンクは設置先の公開 URL を基準にした絶対 URL とし、末尾の `/` まで含める。カテゴリを設定できるようにする必要はなく、最初は全板を「hoshibbs」カテゴリにまとめる。

公開 URL は `config.local.php` の `public_url` で指定する。未設定時はメニューのみ `503` とし、板 URL の個別登録は可能とする。

[5ch の JSON メニュー](https://menu.5ch.io/bbsmenu.json) では、`menu_list` の各カテゴリが `category_content` に板を持つ。`category_number` は文字列、`category_total`（カテゴリ内の板数）・`category`（板が属するカテゴリの番号）・`category_order`（カテゴリ内の表示順）は数値。次は同じ構造で板を1つ載せる例。公式応答には `description` や `last_modify_string` もあったが、対象クライアントでの必須性は未確認。

```json
{
  "last_modify": 1795395600,
  "menu_list": [
    {
      "category_number": "1",
      "category_name": "hoshibbs",
      "category_total": 1,
      "category_content": [
        {
          "category": 1,
          "category_name": "hoshibbs",
          "category_order": 1,
          "url": "https://example.org/general/",
          "directory_name": "general",
          "board_name": "雑談"
        }
      ]
    }
  ]
}
```

`last_modify` はメニューの更新時刻を表す Unix 秒。[JDim のメニュー読み込み実装](https://github.com/JDimproved/JDim/blob/master/src/dbtree/bbsmenu.cpp) に合わせ、HTML では `<B>` と `<A>` を同じ親要素（例: `<body>` 直下）に置く。JDim の一括登録は HTML で検証し、JSON の対応は前提にしない。

## スレッド一覧（subject.txt）

[Monazilla の subject.txt 資料](https://info.5ch.io/index.php/Monazilla/develop/subject.txt) にある従来形式を採用する。1行1スレッドで、行末は LF。件数は最初の投稿を含む（返信なしなら `1`）ため、同じ状態から生成した DAT の行数と一致する。タイトルのタブや BE-ID など、5ch が追加する情報は出力しない。

```text
1795395600.dat<>テストスレッド (2)
```

5ch のキーはスレッド作成時の Unix 秒。hoshibbs では SQLite の内部 `threads.id` と公開キーを分ける。新規キーは現在の Unix 秒と、その板で割り当てた最大キー + 1 のうち大きい方を採る。公開キーは変更・再利用せず、日時の表示には実際の作成時刻を使う。更新前のスレッドには `1000000000 + threads.id` を公開キーとして割り当てる。これは日時ではない。

## スレッド本文（DAT）

[Monazilla の DAT 資料](https://info.5ch.io/index.php/Monazilla/develop/dat) と [5ch の公開 DAT](https://agree.5ch.io/operate/dat/1608930977.dat) に沿う。取得先は `/{board}/dat/{key}.dat`。1投稿を `名前<>メール<>日時と任意のID<>本文<>スレッドタイトル` の5欄で出力する。レス番号は行順で決まり、独立した欄には入れない。タイトルは第1行のみで、返信行も末尾の `<>` で空欄を表す。日時は実際の投稿時刻を日本時間で表示し、ID・BE を提供しない間は付けない。

```text
名無しさん<><>2026/11/23(月) 10:00:00.00<> 最初の投稿 <br> 2行目 <>テストスレッド
名無しさん<><>2026/11/23(月) 10:01:00.00<> 返信 <>
```

`{key}.json` の `comments` 配列は、調査した資料とクライアントで共通の取得形式として確認できなかった。必要になった場合は hoshibbs 独自 API として別に設計し、互換読取の要件にはしない。

## SQLite からの生成と検証

- SQLite を正本とし、板定義と公開スレッドキーを使ってリクエスト時に生成する。投稿日時も SQLite に保存し、更新前の投稿の不明な日時は DAT で空欄にする。
- 投稿は UTF-8 のプレーンテキストとして扱い、出力時に HTML エスケープする。DAT や一覧のフィールドに生の `<>`・CR・LF を混ぜない。DAT の本文はエスケープした後で改行を `<br>` にし、件名やほかの欄では改行を空白にする。CP932 にない文字は数値文字参照にしてから変換し、元の投稿は変更しない。JSON メニューは UTF-8 のまま JSON としてエンコードする。
- 一時 DB で2板・複数投稿を作り、板の取り違えがないこと、`subject.txt` のキーと件数が DAT と一致すること、文字コード・LF・区切り・HTML エスケープを確認する。比較資料として取得した [5ch の subject.txt](https://agree.5ch.io/operate/subject.txt) と DAT は LF 区切りで、同じスレッドの表示件数と DAT の行数が一致した。取得した DAT は CP932 でデコードできたが、厳密な Shift_JIS ではデコードできなかった。
- JDim で板 URL の個別登録と HTML メニューの一括登録を試し、一覧から初回読取、Web からの返信後の再読取まで確認する。全文取得で再読取できるか、[Monazilla のアクセス資料](https://info.5ch.io/index.php/Monazilla/develop/access) にある `Range`・`Last-Modified`・条件付き GET が必要かは、対象版の実リクエストで確認する。ChMate も実機で同じ操作を試し、未確認の機能を対応済みとはしない。
