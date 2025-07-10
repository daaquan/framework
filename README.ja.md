# Phare フレームワーク

Phare は [Phalcon](https://phalcon.io/) 拡張を基盤とした軽量な PHP フレームワークです。ルーティング、データベースアクセス、コマンドラインツールなどのコンポーネントを提供します。

## 特長

- サービスコンテナ
- Eloquent 風 ORM
- コンソールコマンド
- ミドルウェア対応 HTTP カーネル
- 各種パスヘルパを含むヘルパ関数

## インストール

```
composer require phare/framework
```

## Docker 環境

本リポジトリには簡単な Docker 設定が付属しています。Docker をインストール後、
以下のコマンドでコンテナをビルドして実行できます。

```
docker compose run --build app
```

Phalcon 拡張など必要な PHP 拡張がビルドされ、フレームワークやテストを
コンテナ内で実行できます。

## ライセンス

このプロジェクトは MIT ライセンスの下で公開されています。
