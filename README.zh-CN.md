# Phare 框架

Phare 是基于 [Phalcon](https://phalcon.io/) C 扩展构建的轻量级 PHP 框架。
它用 Laravel 风格的约定封装了 Phalcon 的底层 API，提供服务容器、
Eloquent 风格 ORM、中间件管道、控制台命令和辅助函数。

**运行要求:** PHP 8.2+ · `ext-phalcon ^5.9.2`

## 特性

- **服务容器** — 绑定、单例、上下文绑定、标签、解析回调
- **Eloquent 风格 ORM** — 模型、查询构造器、关联关系、预加载、软删除、全局/本地作用域
- **HTTP 内核** — 中间件管道、Request/Response 封装、表单请求验证
- **路由** — 流式路由注册、路由分组、资源路由、路由缓存
- **控制台命令** — Artisan 风格命令与丰富的输入/输出辅助方法
- **数据库** — Schema 构造器、数据库迁移、数据填充器、模型工厂
- **认证** — 基于 Session 的认证（支持 Guard 与事件）
- **缓存** — 文件、Redis、APCu、数组驱动（PSR-16 兼容）
- **辅助函数** — 路径辅助函数及 `app()`、`auth()`、`cache()`、`response()` 等全局函数

## 安装

```bash
composer require phare/framework
```

## 文档

完整文档位于 [`docs/`](docs/index.md) 目录：

- [安装与配置](docs/installation.md)
- [服务容器](docs/container.md)
- [路由](docs/routing.md)
- [HTTP 层](docs/http.md)
- [Eloquent ORM](docs/eloquent.md)
- [数据库与迁移](docs/database.md)
- [控制台命令](docs/console.md)
- [认证](docs/auth.md)
- [缓存](docs/cache.md)

## Docker 环境

安装 [Docker](https://www.docker.com/) 后，运行以下命令即可自动编译 Phalcon 扩展并启动容器：

```bash
docker compose run --build app
```

容器内已安装所有必要的 PHP 扩展，可直接运行框架代码或测试套件。

## 测试

```bash
# 运行全部测试（抑制 PHP 弃用警告）
bin/pest

# 运行单个文件
bin/pest tests/Eloquent/EloquentBuilderTest.php

# 按测试名称过滤
bin/pest --filter="test name"
```

## 代码风格与静态分析

```bash
# 自动修复代码风格（Laravel Pint 预设）
vendor/bin/pint

# 静态分析（PHPStan 8 级）
vendor/bin/phpstan analyse
```

## 许可证

本项目以 [MIT 许可证](LICENSE) 开源。
