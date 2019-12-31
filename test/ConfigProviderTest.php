<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\ConfigProvider;
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputFilterPluginManagerFactory;

final class ConfigProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testProvidesExpectedConfiguration()
    {
        $provider = new ConfigProvider();

        $expected = [
            'abstract_factories' => [
                InputFilterAbstractServiceFactory::class,
            ],
            'aliases' => [
                'InputFilterManager' => InputFilterPluginManager::class,
            ],
            'factories' => [
                InputFilterPluginManager::class => InputFilterPluginManagerFactory::class,
            ],
        ];

        $this->assertEquals($expected, $provider->getDependencyConfig());
    }

    public function testInvocationProvidesDependencyConfiguration()
    {
        $provider = new ConfigProvider();

        $expected = [
            'dependencies' => $provider->getDependencyConfig(),
        ];
        $this->assertEquals($expected, $provider());
    }
}
