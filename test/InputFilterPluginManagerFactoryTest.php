<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Interop\Container\ContainerInterface;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputFilterPluginManagerFactory;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use PHPUnit_Framework_TestCase as TestCase;

class InputFilterPluginManagerFactoryTest extends TestCase
{
    public function testFactoryReturnsPluginManager()
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $factory = new InputFilterPluginManagerFactory();

        $filters = $factory($container, InputFilterPluginManagerFactory::class);
        $this->assertInstanceOf(InputFilterPluginManager::class, $filters);

        if (method_exists($filters, 'configure')) {
            // laminas-servicemanager v3
            $this->assertAttributeSame($container, 'creationContext', $filters);
        } else {
            // laminas-servicemanager v2
            $this->assertSame($container, $filters->getServiceLocator());
        }
    }

    public function pluginProvider()
    {
        return [
            'input' => [InputInterface::class],
            'input-filter' => [InputFilterInterface::class],
        ];
    }

    /**
     * @depends testFactoryReturnsPluginManager
     * @dataProvider pluginProvider
     */
    public function testFactoryConfiguresPluginManagerUnderContainerInterop($pluginType)
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $plugin = $this->prophesize($pluginType)->reveal();

        $factory = new InputFilterPluginManagerFactory();
        $filters = $factory($container, InputFilterPluginManagerFactory::class, [
            'services' => [
                'test' => $plugin,
            ],
        ]);
        $this->assertSame($plugin, $filters->get('test'));
    }

    /**
     * @depends testFactoryReturnsPluginManager
     * @dataProvider pluginProvider
     */
    public function testFactoryConfiguresPluginManagerUnderServiceManagerV2($pluginType)
    {
        $container = $this->prophesize(ServiceLocatorInterface::class);
        $container->willImplement(ContainerInterface::class);

        $plugin = $this->prophesize($pluginType)->reveal();

        $factory = new InputFilterPluginManagerFactory();
        $factory->setCreationOptions([
            'services' => [
                'test' => $plugin,
            ],
        ]);

        $filters = $factory->createService($container->reveal());
        $this->assertSame($plugin, $filters->get('test'));
    }
}
