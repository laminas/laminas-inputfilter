<?php

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\ServiceManager;
use Laminas\ServiceManager\Test\CommonPluginManagerTrait;
use PHPUnit\Framework\TestCase;

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
}
