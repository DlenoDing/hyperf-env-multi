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

use Hyperf\Collection\Arr;
use Hyperf\Config\ProviderConfig;
use Symfony\Component\Finder\Finder;

class ConfigFactory
{
    public function merge($env)
    {
        EnvLoader::load(BASE_PATH, is_string($env) ? $env : null);

        $configPath = BASE_PATH . '/config/';
        $config = $this->readConfig($configPath . 'config.php');
        $serverConfig = $this->readConfig($configPath . 'server.php');
        $autoloadConfig = $this->readPaths([BASE_PATH . '/config/autoload']);

        return array_merge_recursive(ProviderConfig::load(), $serverConfig, $config, ...$autoloadConfig);
    }

    private function readConfig(string $configPath): array
    {
        $config = [];
        if (file_exists($configPath) && is_readable($configPath)) {
            $config = require $configPath;
        }
        return is_array($config) ? $config : [];
    }

    private function readPaths(array $paths): array
    {
        $configs = [];
        $finder = new Finder();
        $finder->files()->in($paths)->name('*.php');
        foreach ($finder as $file) {
            // 与 Hyperf 3.2 ConfigFactory 一致:子目录映射为点分 key(autoload/a/b.php → a.b)
            $config = [];
            $key = implode('.', array_filter([
                str_replace('/', '.', $file->getRelativePath()),
                $file->getBasename('.php'),
            ]));
            Arr::set($config, $key, require $file->getRealPath());
            $configs[] = $config;
        }
        return $configs;
    }
}
