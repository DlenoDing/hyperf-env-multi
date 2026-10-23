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


use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;

use function Hyperf\Support\env;

/**
 * BootApplication 时按 .env.<APP_ENV> 覆盖后的值重新合并应用配置并整体写回 Config。
 *
 * 必须最先执行(最高优先级)：Hyperf 的其他 BootApplication 监听器会在启动阶段改写配置，
 * 如 ExceptionHandlerListener 把 #[ExceptionHandler] 注解处理器合并进 exceptions.handler、
 * RegisterCommandListener 追加 commands。若本监听器排在它们之后，整体写回会把这些结果冲掉
 * (存在 .env.<APP_ENV> 时注解注册的异常处理器全部丢失)。
 */
#[Listener(priority: self::PRIORITY)]
class MultiEnvListener implements ListenerInterface
{
    public const PRIORITY = PHP_INT_MAX;

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    /**
     * @param BootApplication $event
     */
    public function process(object $event): void
    {
        $env = env('APP_ENV');

        if ($env !== null && file_exists($envPath = BASE_PATH . '/.env.' . $env) && ApplicationContext::hasContainer()) {
            $container = ApplicationContext::getContainer();

            $factory = $container->get(ConfigFactory::class);

            $config = $container->get(ConfigInterface::class);

            $newConfig = $factory->merge($env);

            foreach ($newConfig as $key => $value) {
                $config->set($key, $value);
            }
        }
    }
}
