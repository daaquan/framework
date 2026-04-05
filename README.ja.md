# Phare フレームワーク

Phare は [Phalcon](https://phalcon.io/) C 拡張を基盤とした軽量 PHP フレームワークです。
Phalcon の低レイヤー API を Laravel ライクな規約でラップし、サービスコンテナ・
Eloquent 風 ORM・ミドルウェアパイプライン・コンソールコマンド・ヘルパ関数を提供します。

**動作要件:** PHP 8.2+ · `ext-phalcon ^5.9.2`

## 特長

- **サービスコンテナ** — バインディング、シングルトン、コンテキスチュアルバインディング、タグ、解決コールバック
- **Eloquent 風 ORM** — モデル、クエリビルダ、リレーション、イーガーローディング、ソフトデリート、スコープ
- **HTTP カーネル** — ミドルウェアパイプライン、Request/Response ラッパー、フォームリクエストバリデーション
- **ルーティング** — 流暢なルート登録、グループ、リソースルート、ルートキャッシュ
- **コンソールコマンド** — Artisan 風コマンドと豊富な入出力ヘルパ
- **データベース** — スキーマビルダ、マイグレーション、シーダ、モデルファクトリ
- **認証** — セッションベース認証（ガード・イベント対応）
- **キャッシュ** — ファイル・Redis・APCu・配列ドライバ（PSR-16 準拠）
- **ヘルパ関数** — パスヘルパ、`app()`、`auth()`、`cache()`、`response()` など

## インストール

```bash
composer require phare/framework
```

## ドキュメント

完全なドキュメントは [`docs/`](docs/index.md) ディレクトリにあります:

- [インストールと設定](docs/installation.md)
- [サービスコンテナ](docs/container.md)
- [ルーティング](docs/routing.md)
- [HTTP レイヤー](docs/http.md)
- [Eloquent ORM](docs/eloquent.md)
- [データベースとマイグレーション](docs/database.md)
- [コンソールコマンド](docs/console.md)
- [認証](docs/auth.md)
- [キャッシュ](docs/cache.md)

## Docker 環境

[Docker](https://www.docker.com/) をインストール後、以下のコマンドで Phalcon 拡張を含む
環境を自動構築できます。

```bash
docker compose run --build app
```

コンテナ内ではすべての PHP 拡張が利用可能で、フレームワークのコードやテストを
そのまま実行できます。

## テスト

```bash
# テストスイート全体 (PHP 非推奨警告を抑制)
bin/pest

# 単一ファイル
bin/pest tests/Eloquent/EloquentBuilderTest.php

# テスト名でフィルタ
bin/pest --filter="test name"
```

## コードスタイルと静的解析

```bash
# コードスタイル自動修正 (Laravel Pint プリセット)
vendor/bin/pint

# 静的解析 (PHPStan レベル 8)
vendor/bin/phpstan analyse
```

## ライセンス

このプロジェクトは [MIT ライセンス](LICENSE) の下で公開されています。
