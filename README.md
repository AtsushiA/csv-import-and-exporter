# CSV Import and Exporter

WordPress プラグイン。投稿タイプごとに CSV 形式でエクスポート／インポートができます。カスタムフィールド・カスタムタクソノミーに対応し、件数や日付範囲での絞り込みも可能です。

**Version:** 1.0.3
**License:** GPLv2 or later

---

## 機能

- 全投稿タイプの CSV エクスポート（管理画面 + WP-CLI）
- CSV インポート
- カスタムフィールド・カスタムタクソノミー対応
- 投稿ステータス・件数・日付範囲・並び順の指定
- 文字コード変換（UTF-8 / Shift_JIS）
- WP-CLI コマンド対応（`wp csv-export export`）

---

## インストール

1. `csv-import-and-exporter` フォルダを `/wp-content/plugins/` に配置する。
2. WordPress 管理画面の「プラグイン」メニューから有効化する。
3. 管理画面の **ツール > CSV エクスポート** / **ツール > CSV インポート** から利用できます。

---

## 管理画面でのエクスポート

「ツール > CSV エクスポート」にアクセスし、エクスポートしたい投稿タイプのタブを選択して設定します。

| 設定項目 | 内容 |
|---------|------|
| フィールド | スラッグ・タイトル・本文・抜粋・投稿者・公開日時・変更日時 |
| サムネイル | アイキャッチ画像 URL を含める |
| post_parent / menu_order | ページ階層・並び順を含める |
| ステータス | publish / pending / draft / future / private / trash / inherit |
| タグ | 投稿タグを含める |
| タクソノミー | 登録済みカスタムタクソノミーを選択 |
| カスタムフィールド | 登録済みカスタムフィールドを選択 |
| 件数 / 開始位置 | 0 = すべてダウンロード |
| 並び順 | 公開日 DESC / ASC |
| 公開日・変更日の期間指定 | yyyy-mm-dd 形式で From / To を指定 |
| 文字コード | UTF-8 / Shift_JIS |

---

## WP-CLI でのエクスポート

### 基本構文

```bash
wp csv-export export [オプション]
```

### オプション一覧

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `--post-type=<slug>` | `post` | エクスポートする投稿タイプ |
| `--status=<statuses>` | 全ステータス | カンマ区切りのステータス（例: `publish,draft`） |
| `--fields=<fields>` | 全フィールド | カンマ区切りのフィールド名 |
| `--taxonomies=<slugs>` | 全タクソノミー | カンマ区切りのタクソノミースラッグ |
| `--cf-fields=<keys>` | 全カスタムフィールド | カンマ区切りのカスタムフィールドキー |
| `--thumbnail` | — | アイキャッチ画像 URL を含める |
| `--post-parent` | — | post_parent を含める |
| `--menu-order` | — | menu_order を含める |
| `--tags` | — | 投稿タグを含める |
| `--limit=<n>` | `0`（全件） | 最大取得件数 |
| `--offset=<n>` | `0` | 開始位置 |
| `--order=<ASC\|DESC>` | `DESC` | 公開日の並び順 |
| `--date-from=<Y-m-d>` | — | 公開日の開始日 |
| `--date-to=<Y-m-d>` | — | 公開日の終了日 |
| `--modified-from=<Y-m-d>` | — | 変更日の開始日 |
| `--modified-to=<Y-m-d>` | — | 変更日の終了日 |
| `--encoding=<enc>` | `UTF-8` | 出力文字コード（`UTF-8` または `SJIS`） |
| `--output=<path>` | stdout | 出力ファイルパス |

### 使用例

```bash
# post タイプをすべて stdout へ出力（デフォルト）
wp csv-export export

# カスタム投稿タイプをファイルに保存
wp csv-export export --post-type=record --output=records.csv

# 公開済みのみ・特定フィールドを 100 件
wp csv-export export --post-type=record --status=publish --fields=post_title,post_name --limit=100

# タクソノミー・日付範囲を絞り込む
wp csv-export export --post-type=record --taxonomies=genre,country --date-from=2024-01-01 --date-to=2024-12-31

# stdout をリダイレクトでファイル保存（Shift_JIS）
wp csv-export export --post-type=record --encoding=SJIS > records_sjis.csv
```

> `--post-type` を省略した場合は `post` タイプが対象になります。
> `--status`・`--taxonomies`・`--cf-fields` を省略した場合はすべて出力されます。

---

## フィルターフック

エクスポートデータを加工するためのフィルターが用意されています。

| フィルター名 | 対象 |
|------------|------|
| `wp_csv_exporter_post_name` | スラッグ |
| `wp_csv_exporter_post_title` | タイトル |
| `wp_csv_exporter_post_content` | 本文 |
| `wp_csv_exporter_post_excerpt` | 抜粋 |
| `wp_csv_exporter_post_status` | ステータス |
| `wp_csv_exporter_post_author` | 投稿者 |
| `wp_csv_exporter_post_date` | 公開日時 |
| `wp_csv_exporter_post_modified` | 変更日時 |
| `wp_csv_exporter_thumbnail_url` | アイキャッチ画像 URL |
| `wp_csv_exporter_post_tags` | タグ（配列） |
| `wp_csv_exporter_post_category` | カテゴリー（配列） |
| `wp_csv_exporter_tax_{taxonomy}` | カスタムタクソノミー（配列） |
| `wp_csv_exporter_{custom_field_key}` | カスタムフィールド |

### 例: タイトルに投稿 ID を付与する

```php
add_filter( 'wp_csv_exporter_post_title', function( $post_title, $post_id ) {
    return $post_id . ': ' . $post_title;
}, 10, 2 );
```

### 例: カスタムタクソノミー `genre` の値を加工する

```php
add_filter( 'wp_csv_exporter_tax_genre', function( $term_values, $post_id ) {
    return array_map( function( $v ) { return 'Genre:' . $v; }, $term_values );
}, 10, 2 );
```

---

## ファイル構成

```
csv-import-and-exporter/
├── csv-import-and-exporter.php      メインプラグインファイル
├── classes/
│   ├── csviae-base.php              抽象基底クラス（共通ユーティリティ）
│   ├── class-csviae-exporter.php    コアエクスポートロジック
│   └── class-csviae-cli-command.php WP-CLI コマンドクラス
├── admin/
│   ├── index.php                    管理画面エントリーポイント
│   ├── admin.php                    エクスポート設定フォーム
│   └── download.php                 AJAX ダウンロードハンドラー
├── import/
│   └── rs-csv-importer.php          インポート処理
├── download/                        一時 CSV ファイル格納ディレクトリ（要書き込み権限）
├── css/
├── js/
└── README.md
```

---

## Changelog

### 1.0.3 — 2026-03-16
- WP-CLI コマンド `wp csv-export export` を追加
- エクスポートコアロジックを `CSVIAE_Exporter` クラスに分離
- `admin/download.php` を `CSVIAE_Exporter` クラス使用に全面リファクタリング
- `wce-with-post-type` アドオン不要化：全投稿タイプを標準でエクスポート対応
- PHP 8 Fatal Error 修正（`wp_kses()` → `esc_html()`）
- CSV に `-1` が付加されるバグ修正（`readfile()` 後に `exit` を追加）
- メモリ枯渇対策：投稿処理ごとに `clean_post_cache()` を呼び出し
- PHPCS コーディング規約準拠（インデント・ドキュメントコメント・ケイパビリティ指定等）

### 1.0.2 — 2026-03-15
- バグ修正・PHPCS リファクタリング

### 1.0.0 — 2023-04-14
- 初回リリース
