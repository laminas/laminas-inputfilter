<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Interop\Container\ContainerInterface; // phpcs:ignore
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\Module;
use PHPUnit\Framework\TestCase;

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
        $this->assertArrayHasKey('service_manager', $config);

        // Input filters
        $this->assertArrayHasKey('input_filters', $config);
    }

    public function testServiceManagerConfigShouldContainInputFilterManager(): void
    {
        $config = $this->module->getConfig();

        $this->assertArrayHasKey(
            InputFilterPluginManager::class,
            $config['service_manager']['factories']
        );
    }

    public function testServiceManagerConfigShouldContainAliasForInputFilterManager(): void
    {
        $config = $this->module->getConfig();

        $this->assertArrayHasKey(
            'InputFilterManager',
            $config['service_manager']['aliases']
        );
    }

    public function testInputFilterConfigShouldContainAbstractServiceFactory(): void
    {
        $config = $this->module->getConfig();

        $this->assertContains(
            InputFilterAbstractServiceFactory::class,
            $config['input_filters']['abstract_factories']
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

        $this->module->init($moduleManager);
    }
}
