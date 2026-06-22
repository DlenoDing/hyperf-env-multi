# dleno/hyperf-env-multi

Hyperf 多环境 `.env` 加载组件。

该组件在 Hyperf 默认 `.env` 基础上，按当前 `APP_ENV` 加载 `.env.<APP_ENV>`，并提供可显式调用的 `EnvLoader`。当其他包需要在自己的 `ConfigProvider` 中读取 `env()` 时，可以先调用 `EnvLoader`，确保读到的是环境覆盖后的值。

## 功能

- 默认加载 `.env`。
- 当 `APP_ENV` 有值时，额外加载 `.env.<APP_ENV>`。
- `.env.<APP_ENV>` 中的同名变量会覆盖 `.env` 中的值。
- `BootApplication` 阶段通过 `MultiEnvListener` 重新合并项目配置文件，让 `config/config.php`、`config/server.php`、`config/autoload/*.php` 读取到环境覆盖后的值。
- 提供 `Dleno\HyperfEnvMulti\EnvLoader::load()`，供需要在 `BootApplication` 之前读取 `env()` 的包自行调用。
- 不清理 `Hyperf\Config\ProviderConfig` 缓存，避免对全局配置构建流程产生不可控影响。

## 安装

```bash
composer require dleno/hyperf-env-multi
```

Composer 会通过 `extra.hyperf.config` 自动注册 `ConfigProvider`。

## 加载规则

假设项目根目录存在：

```text
.env
.env.local
```

`.env`：

```dotenv
APP_ENV=local
ENABLE_WS=false
REDIS_HOST=localhost
```

`.env.local`：

```dotenv
ENABLE_WS=true
REDIS_HOST=redis
```

运行时结果：

```text
APP_ENV=local
ENABLE_WS=true
REDIS_HOST=redis
```

`.env.<APP_ENV>` 不存在时不会报错，也不会改变 `.env` 的加载结果。

## 显式加载

如果某个包会在自己的 `ConfigProvider::__invoke()` 中调用 `env()`，而这个调用发生在 Hyperf 完成 `BootApplication` 事件之前，就应在读取 `env()` 前显式调用：

```php
<?php

declare(strict_types=1);

namespace App;

use Dleno\HyperfEnvMulti\EnvLoader;

use function Hyperf\Support\env;

class ConfigProvider
{
    public function __invoke(): array
    {
        EnvLoader::load(BASE_PATH);

        return [
            'middlewares' => env('ENABLE_HTTP', false) ? [
                'http' => [
                    // ...
                ],
            ] : [],
        ];
    }
}
```

这样该包读取到的 `env()` 值就是 `.env.<APP_ENV>` 覆盖后的值。

## 配置合并边界

`MultiEnvListener` 会在 `BootApplication` 阶段重新合并应用配置文件，但不会清理或重建 Hyperf 的 `ProviderConfig` 静态缓存。

因此：

- 应用侧 `config/config.php`、`config/server.php`、`config/autoload/*.php` 会按 `.env.<APP_ENV>` 覆盖后的值重新读取。
- 包侧 `ConfigProvider` 如果需要在早期读取 `env()`，应由该包自行调用 `EnvLoader::load(BASE_PATH)`。
- 组件不会通过 `ProviderConfig::clear()` 全局重建 Provider 配置，避免影响其他包的加载顺序和副作用。

## 启动示例

通过 `.env` 指定环境：

```dotenv
APP_ENV=local
```

命令行临时指定环境：

```bash
APP_ENV=production php bin/hyperf.php start
```

Docker 指定环境：

```bash
docker run --env APP_ENV=production -d -p 9501:9501 your-image
```

PHPUnit 指定环境：

```xml
<phpunit>
    <php>
        <env name="APP_ENV" value="testing"/>
    </php>
</phpunit>
```

## 注意事项

- 推荐把公共配置放在 `.env`，环境差异配置放在 `.env.<APP_ENV>`。
- `EnvLoader::load($basePath, $env)` 对同一个 `$basePath + $env` 做进程级幂等处理，避免重复加载。
- 如果进程环境中已经存在 `APP_ENV`，会优先按当前进程环境值决定加载哪个 `.env.<APP_ENV>`。
- 该组件不会调用 `ProviderConfig::clear()`；如果某个包需要早期读取环境变量，应由该包自行调用 `EnvLoader::load()`。

## 发布

推送 `v*` tag 后，GitHub Actions 会自动创建 GitHub Release。Composer 包版本仍由 Git tag 决定。

```bash
git tag v3.1.0
git push origin v3.1.0
```
