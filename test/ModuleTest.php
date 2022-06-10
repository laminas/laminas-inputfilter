<?php

namespace LaminasTest\InputFilter;

use Interop\Container\ContainerInterface; // phpcs:ignore
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\Module;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ModuleTest extends TestCase
{
    use ProphecyTrait;

    /** @var Module */
    private $module;

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
        $serviceListener = $this->prophesize(TestAsset\ServiceListenerInterface::class);
        $serviceListener->addServiceManager(
            'InputFilterManager',
            'input_filters',
            'Laminas\ModuleManager\Feature\InputFilterProviderInterface',
            'getInputFilterConfig'
        )->shouldBeCalled();

        // Container
        $container = $this->prophesize(ContainerInterface::class);
        $container->get('ServiceListener')->will([$serviceListener, 'reveal']);

        // Event
        $event = $this->prophesize(TestAsset\ModuleEventInterface::class);
        $event->getParam('ServiceManager')->will([$container, 'reveal']);

        // Module manager
        $moduleManager = $this->prophesize(TestAsset\ModuleManagerInterface::class);
        $moduleManager->getEvent()->will([$event, 'reveal']);

        $this->assertNull($this->module->init($moduleManager->reveal()));
    }
}
