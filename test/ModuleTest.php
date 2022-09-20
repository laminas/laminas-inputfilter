<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\Module;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ModuleTest extends TestCase
{
    private Module $module;

    protected function setUp(): void
    {
        $this->module = new Module();
    }

    public function testGetConfigMethodShouldReturnExpectedKeys(): void
    {
        $config = $this->module->getConfig();

        // Service manager
        self::assertArrayHasKey('service_manager', $config);

        // Input filters
        self::assertArrayHasKey('input_filters', $config);
    }

    public function testServiceManagerConfigShouldContainInputFilterManager(): void
    {
        $config = $this->module->getConfig();

        self::assertArrayHasKey(
            InputFilterPluginManager::class,
            $config['service_manager']['factories'] ?? []
        );
    }

    public function testServiceManagerConfigShouldContainAliasForInputFilterManager(): void
    {
        $config = $this->module->getConfig();

        self::assertArrayHasKey(
            'InputFilterManager',
            $config['service_manager']['aliases'] ?? []
        );
    }

    public function testInputFilterConfigShouldContainAbstractServiceFactory(): void
    {
        $config = $this->module->getConfig();

        self::assertContains(
            InputFilterAbstractServiceFactory::class,
            $config['input_filters']['abstract_factories'] ?? []
        );
    }

    public function testInitMethodShouldRegisterPluginManagerSpecificationWithServiceListener(): void
    {
        // Service listener
        $serviceListener = $this->createMock(TestAsset\ServiceListenerInterface::class);
        $serviceListener->expects(self::once())
            ->method('addServiceManager')
            ->with(
                'InputFilterManager',
                'input_filters',
                'Laminas\ModuleManager\Feature\InputFilterProviderInterface',
                'getInputFilterConfig'
            );

        // Container
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())
            ->method('get')
            ->with('ServiceListener')
            ->willReturn($serviceListener);

        // Event
        $event = $this->createMock(TestAsset\ModuleEventInterface::class);
        $event->expects(self::once())
            ->method('getParam')
            ->with('ServiceManager')
            ->willReturn($container);

        // Module manager
        $moduleManager = $this->createMock(TestAsset\ModuleManagerInterface::class);
        $moduleManager->expects(self::once())
            ->method('getEvent')
            ->willReturn($event);

        /** @psalm-suppress InvalidArgument Prevents dev dependency on the Module manager component */
        $this->module->init($moduleManager);
    }
}
