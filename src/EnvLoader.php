<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Dleno\HyperfEnvMulti;

use Dotenv\Dotenv;
use Dotenv\Repository\Adapter;
use Dotenv\Repository\RepositoryBuilder;
use Hyperf\Support\DotenvManager;

use function Hyperf\Support\env;

class EnvLoader
{
    private static array $loaded = [];

    public static function load(string $basePath, null|string $env = null): void
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        if ($basePath === '') {
            return;
        }

        if (file_exists($basePath . '/.env')) {
            DotenvManager::load([$basePath]);
        }

        $env ??= env('APP_ENV');
        if (! is_string($env) || $env === '') {
            return;
        }

        $key = $basePath . '|' . $env;
        if (isset(self::$loaded[$key])) {
            return;
        }

        $envFile = '.env.' . $env;
        if (! file_exists($basePath . '/' . $envFile)) {
            return;
        }

        $repository = RepositoryBuilder::createWithNoAdapters()
            ->addReader(Adapter\PutenvAdapter::class)
            ->addWriter(Adapter\PutenvAdapter::class)
            ->make();

        Dotenv::create($repository, [$basePath], $envFile)->load();

        self::$loaded[$key] = true;
    }
}
