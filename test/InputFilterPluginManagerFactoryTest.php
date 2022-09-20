<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputFilterPluginManagerFactory;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionObject;

class InputFilterPluginManagerFactoryTest extends TestCase
{
    public function testFactoryReturnsPluginManager(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $factory   = new InputFilterPluginManagerFactory();

        $filters = $factory($container, InputFilterPluginManagerFactory::class);
        $this->assertInstanceOf(InputFilterPluginManager::class, $filters);

        $r = new ReflectionObject($filters);
        $p = $r->getProperty('creationContext');
        $p->setAccessible(true);
        $this->assertSame($container, $p->getValue($filters));
    }

    /** @psalm-return array<string, array{0: class-string<InputInterface>}> */
    public function pluginProvider(): array
    {
        return [
            'input'        => [InputInterface::class],
            'input-filter' => [InputFilterInterface::class],
        ];
    }

    /**
     * @depends testFactoryReturnsPluginManager
     * @dataProvider pluginProvider
     * @psalm-param class-string $pluginType
     */
    public function testFactoryConfiguresPluginManagerUnderContainerInterop(string $pluginType): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $plugin    = $this->createMock($pluginType);

        $factory = new InputFilterPluginManagerFactory();
        $filters = $factory($container, InputFilterPluginManagerFactory::class, [
            'services' => [
                'test' => $plugin,
            ],
        ]);
        $this->assertSame($plugin, $filters->get('test'));
    }

    public function testConfiguresInputFilterServicesWhenFound(): void
    {
        $inputFilter = $this->createMock(InputFilterInterface::class);
        $config      = [
            'input_filters' => [
                'aliases'   => [
                    'test' => 'test-too',
                ],
                'factories' => [
                    'test-too' => static fn (): InputFilterInterface => $inputFilter,
                ],
            ],
        ];

        $container = $this->createMock(ServiceLocatorInterface::class);
        $container->method('has')
            ->willReturnMap([
                ['ServiceListener', false],
                ['config', true],
            ]);
        $container->method('get')
            ->with('config')
            ->willReturn($config);

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container);

        $this->assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
        $this->assertTrue($inputFilters->has('test'));
        $this->assertSame($inputFilter, $inputFilters->get('test'));
        $this->assertTrue($inputFilters->has('test-too'));
        $this->assertSame($inputFilter, $inputFilters->get('test-too'));
    }

    public function testDoesNotConfigureInputFilterServicesWhenServiceListenerPresent(): void
    {
        $container = $this->createMock(ServiceLocatorInterface::class);
        $container->expects(self::once())
            ->method('has')
            ->with('ServiceListener')
            ->willReturn(true);

        $container->expects(self::never())->method('get');

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container);

        $this->assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
        $this->assertFalse($inputFilters->has('test'));
        $this->assertFalse($inputFilters->has('test-too'));
    }

    public function testDoesNotConfigureInputFilterServicesWhenConfigServiceNotPresent(): void
    {
        $container = $this->createMock(ServiceLocatorInterface::class);
        $container->method('has')
            ->willReturnMap([
                ['ServiceListener', false],
                ['config', false],
            ]);
        $container->expects(self::never())->method('get');

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container);

        $this->assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
    }

    public function testDoesNotConfigureInputFilterServicesWhenConfigServiceDoesNotContainInputFiltersConfig(): void
    {
        $container = $this->createMock(ServiceLocatorInterface::class);
        $container->method('has')
            ->willReturnMap([
                ['ServiceListener', false],
                ['config', true],
            ]);
        $container->expects(self::once())
            ->method('get')
            ->with('config')
            ->willReturn(['foo' => 'bar']);

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container);

        $this->assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
        $this->assertFalse($inputFilters->has('foo'));
    }
}
