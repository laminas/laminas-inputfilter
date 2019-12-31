<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\ServiceManager;
use Laminas\ServiceManager\Test\CommonPluginManagerTrait;
use PHPUnit_Framework_TestCase as TestCase;

class InputFilterPluginManagerCompatibilityTest extends TestCase
{
    use CommonPluginManagerTrait;

    public function testInstanceOfMatches()
    {
        $this->markTestSkipped("InputFilterPluginManager accepts multiple instances");
    }

    protected function getPluginManager()
    {
        return new InputFilterPluginManager(new ServiceManager());
    }

    protected function getV2InvalidPluginException()
    {
        return RuntimeException::class;
    }

    protected function getInstanceOf()
    {
        // InputFilterManager accepts multiple instance types
        return;
    }

    public function testConstructorArgumentsAreOptionalUnderV2()
    {
        $plugins = $this->getPluginManager();
        if (method_exists($plugins, 'configure')) {
            $this->markTestSkipped('laminas-servicemanager v3 plugin managers require a container argument');
        }

        $plugins = new InputFilterPluginManager();
        $this->assertInstanceOf(InputFilterPluginManager::class, $plugins);
    }

    public function testConstructorAllowsConfigInstanceAsFirstArgumentUnderV2()
    {
        $plugins = $this->getPluginManager();
        if (method_exists($plugins, 'configure')) {
            $this->markTestSkipped('laminas-servicemanager v3 plugin managers require a container argument');
        }

        $plugins = new InputFilterPluginManager(new Config([]));
        $this->assertInstanceOf(InputFilterPluginManager::class, $plugins);
    }
}
