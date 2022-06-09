<?php

namespace LaminasTest\InputFilter;

use Interop\Container\ContainerInterface;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputFilterPluginManagerFactory;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use ReflectionObject;

class InputFilterPluginManagerFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testFactoryReturnsPluginManager(): void
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
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
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $plugin    = $this->prophesize($pluginType)->reveal();

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
        $inputFilter = $this->prophesize(InputFilterInterface::class)->reveal();
        $config      = [
            'input_filters' => [
                'aliases'   => [
                    'test' => 'test-too',
                ],
                'factories' => [
                    'test-too' => function () use ($inputFilter) {
                        return $inputFilter;
                    },
                ],
            ],
        ];

        $container = $this->prophesize(ServiceLocatorInterface::class);
        $container->willImplement(ContainerInterface::class);

        $container->has('ServiceListener')->willReturn(false);
        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn($config);

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container->reveal());

        $this->assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
        $this->assertTrue($inputFilters->has('test'));
        $this->assertSame($inputFilter, $inputFilters->get('test'));
        $this->assertTrue($inputFilters->has('test-too'));
        $this->assertSame($inputFilter, $inputFilters->get('test-too'));
    }

    public function testDoesNotConfigureInputFilterServicesWhenServiceListenerPresent(): void
    {
        $container = $this->prophesize(ServiceLocatorInterface::class);
        $container->willImplement(ContainerInterface::class);

        $container->has('ServiceListener')->willReturn(true);
        $container->has('config')->shouldNotBeCalled();
        $container->get('config')->shouldNotBeCalled();

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container->reveal());

        $this->assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
        $this->assertFalse($inputFilters->has('test'));
        $this->assertFalse($inputFilters->has('test-too'));
    }

    public function testDoesNotConfigureInputFilterServicesWhenConfigServiceNotPresent(): void
    {
        $container = $this->prophesize(ServiceLocatorInterface::class);
        $container->willImplement(ContainerInterface::class);

        $container->has('ServiceListener')->willReturn(false);
        $container->has('config')->willReturn(false);
        $container->get('config')->shouldNotBeCalled();

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container->reveal());

        $this->assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
    }

    public function testDoesNotConfigureInputFilterServicesWhenConfigServiceDoesNotContainInputFiltersConfig(): void
    {
        $container = $this->prophesize(ServiceLocatorInterface::class);
        $container->willImplement(ContainerInterface::class);

        $container->has('ServiceListener')->willReturn(false);
        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn(['foo' => 'bar']);

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container->reveal());

        $this->assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
        $this->assertFalse($inputFilters->has('foo'));
    }
}
