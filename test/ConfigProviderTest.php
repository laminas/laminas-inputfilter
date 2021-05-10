<?php

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\ConfigProvider;
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputFilterPluginManagerFactory;
use PHPUnit\Framework\TestCase;

final class ConfigProviderTest extends TestCase
{
    public function testProvidesExpectedConfiguration()
    {
        $provider = new ConfigProvider();

        $expected = [
            'aliases' => [
                'InputFilterManager' => InputFilterPluginManager::class,
                'Zend\InputFilter\InputFilterPluginManager' => InputFilterPluginManager::class,
            ],
            'factories' => [
                InputFilterPluginManager::class => InputFilterPluginManagerFactory::class,
            ],
        ];

        $this->assertEquals($expected, $provider->getDependencyConfig());
    }

    public function testProvidesExpectedInputFilterConfiguration()
    {
        $provider = new ConfigProvider();

        $expected = [
            'abstract_factories' => [
                InputFilterAbstractServiceFactory::class,
            ],
        ];

        $this->assertEquals($expected, $provider->getInputFilterConfig());
    }

    public function testInvocationProvidesDependencyConfiguration()
    {
        $provider = new ConfigProvider();

        $expected = [
            'dependencies' => $provider->getDependencyConfig(),
            'input_filters' => $provider->getInputFilterConfig(),
        ];
        $this->assertEquals($expected, $provider());
    }
}
