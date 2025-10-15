<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputFilterPluginManagerFactory;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class InputFilterPluginManagerFactoryTest extends TestCase
{
    public function testFactoryReturnsPluginManager(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $factory   = new InputFilterPluginManagerFactory();

        $filters = $factory($container, InputFilterPluginManagerFactory::class);
        self::assertInstanceOf(InputFilterPluginManager::class, $filters);
    }

    /** @psalm-return array<string, array{0: class-string}> */
    public static function pluginProvider(): array
    {
        return [
            'input'        => [InputInterface::class],
            'input-filter' => [InputFilterInterface::class],
        ];
    }

    /**
     * @psalm-param class-string $pluginType
     */
    #[DataProvider('pluginProvider')]
    #[Depends('testFactoryReturnsPluginManager')]
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
        self::assertSame($plugin, $filters->get('test'));
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
        $container->expects(self::once())
            ->method('has')
            ->with('config')
            ->willReturn(true);
        $container->expects(self::once())
            ->method('get')
            ->with('config')
            ->willReturn($config);

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container);

        self::assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
        self::assertTrue($inputFilters->has('test'));
        self::assertSame($inputFilter, $inputFilters->get('test'));
        self::assertTrue($inputFilters->has('test-too'));
        self::assertSame($inputFilter, $inputFilters->get('test-too'));
    }

    public function testDoesNotConfigureInputFilterServicesWhenConfigServiceNotPresent(): void
    {
        $container = $this->createMock(ServiceLocatorInterface::class);
        $container->expects(self::once())
            ->method('has')
            ->with('config')
            ->willReturn(false);
        $container->expects(self::never())->method('get');

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container);

        self::assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
    }

    public function testDoesNotConfigureInputFilterServicesWhenConfigServiceDoesNotContainInputFiltersConfig(): void
    {
        $container = $this->createMock(ServiceLocatorInterface::class);
        $container->expects(self::once())
            ->method('has')
            ->with('config')
            ->willReturn(true);
        $container->expects(self::once())
            ->method('get')
            ->with('config')
            ->willReturn(['foo' => 'bar']);

        $factory      = new InputFilterPluginManagerFactory();
        $inputFilters = $factory($container);

        self::assertInstanceOf(InputFilterPluginManager::class, $inputFilters);
        self::assertFalse($inputFilters->has('foo'));
    }
}
