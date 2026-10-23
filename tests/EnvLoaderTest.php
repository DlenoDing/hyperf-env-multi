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

namespace HyperfTest;

use Dleno\HyperfEnvMulti\ConfigFactory;
use Dleno\HyperfEnvMulti\EnvLoader;
use Dleno\HyperfEnvMulti\MultiEnvListener;
use Hyperf\Config\Config;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\EventDispatcher;
use Hyperf\Event\ListenerProvider;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\Config\ProviderConfig;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function Hyperf\Support\env;

/**
 * @internal
 */
#[CoversNothing]
class EnvLoaderTest extends TestCase
{
    public function testEnvFileOverridesBaseEnv(): void
    {
        EnvLoader::load(BASE_PATH);

        $this->assertSame('testing', env('APP_ENV'));
        $this->assertSame('testing', env('ENV_MULTI_NAME'));
        $this->assertSame('yes', env('ENV_MULTI_ONLY_BASE'));
    }

    public function testMissingEnvFileIsIgnored(): void
    {
        //不依赖其他用例的执行顺序:先记下当前值,加载不存在的环境文件后应保持不变
        EnvLoader::load(BASE_PATH);
        $before = env('ENV_MULTI_NAME');

        EnvLoader::load(BASE_PATH, 'not-exists');

        $this->assertNotNull($before);
        $this->assertSame($before, env('ENV_MULTI_NAME'));
        $this->assertSame('yes', env('ENV_MULTI_ONLY_BASE'));
    }

    public function testMergeReadsOverriddenValuesAndNestedAutoloadPaths(): void
    {
        // 夹具项目没有 composer.lock,直接预置 ProviderConfig 静态缓存代替扫描已安装包
        (new ReflectionClass(ProviderConfig::class))->setStaticPropertyValue('providerConfigs', ['from_provider' => true]);

        try {
            $config = (new ConfigFactory())->merge('testing');
        } finally {
            ProviderConfig::clear();
        }

        $this->assertTrue($config['from_provider']);
        $this->assertSame('testing', $config['app_name']);
        $this->assertSame('testing', $config['demo']['name']);
        $this->assertTrue($config['sub']['nested']['enabled']);
    }

    public function testListenerOverridesContainerConfigOnBootApplication(): void
    {
        $this->assertSame([BootApplication::class], (new MultiEnvListener())->listen());

        //模拟 .env 加载前已构建的旧配置,BootApplication 后应被 .env.testing 覆盖后的值替换
        $config = new Config(['app_name' => 'stale', 'demo' => ['name' => 'stale']]);
        $container = new Container(new DefinitionSource([ConfigInterface::class => fn () => $config]));
        ApplicationContext::setContainer($container);
        (new ReflectionClass(ProviderConfig::class))->setStaticPropertyValue('providerConfigs', ['from_provider' => true]);

        try {
            EnvLoader::load(BASE_PATH);
            (new MultiEnvListener())->process(new BootApplication());
        } finally {
            ProviderConfig::clear();
        }

        $this->assertSame('testing', $config->get('app_name'));
        $this->assertSame('testing', $config->get('demo.name'));
        $this->assertTrue($config->get('sub.nested.enabled'));
    }

    public function testListenerRunsBeforeOtherBootListenersAndKeepsTheirConfig(): void
    {
        $attrs = (new ReflectionClass(MultiEnvListener::class))->getAttributes(Listener::class);
        $this->assertCount(1, $attrs);
        $priority = $attrs[0]->newInstance()->priority;
        $this->assertSame(PHP_INT_MAX, $priority);

        $config = new Config(['app_name' => 'stale', 'exceptions' => ['handler' => ['http' => ['FromConfig']]]]);
        $container = new Container(new DefinitionSource([ConfigInterface::class => fn () => $config]));
        ApplicationContext::setContainer($container);
        (new ReflectionClass(ProviderConfig::class))->setStaticPropertyValue('providerConfigs', ['from_provider' => true]);

        //模拟 ExceptionHandlerListener:默认优先级，在启动阶段把注解注册的处理器写进配置。
        //先注册它、后注册 MultiEnvListener(与注解监听器晚于配置监听器注册的真实顺序一致)，仅靠优先级决定先后。
        $provider = new ListenerProvider();
        $provider->on(BootApplication::class, static function () use ($config) {
            $handlers   = $config->get('exceptions.handler.http', []);
            $handlers[] = 'FromAnnotation';
            $config->set('exceptions.handler.http', $handlers);
        });
        $provider->on(BootApplication::class, [new MultiEnvListener(), 'process'], $priority);

        try {
            EnvLoader::load(BASE_PATH);
            (new EventDispatcher($provider))->dispatch(new BootApplication());
        } finally {
            ProviderConfig::clear();
        }

        $this->assertSame('testing', $config->get('app_name'));
        $this->assertContains('FromAnnotation', (array) $config->get('exceptions.handler.http'));
    }
}
