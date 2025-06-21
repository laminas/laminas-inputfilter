<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\ServiceManager\Test\CommonPluginManagerTrait;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit\Framework\TestCase;

final class InputFilterPluginManagerCompatibilityTest extends TestCase
{
    use CommonPluginManagerTrait;

    public function testInstanceOfMatches(): void
    {
        $this->markTestSkipped("InputFilterPluginManager accepts multiple instances");
    }

    protected static function getPluginManager(): InputFilterPluginManager
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService(
            Factory::class,
            new Factory(
                new FilterPluginManager($serviceManager),
                new ValidatorPluginManager($serviceManager),
                new InputFilterPluginManager($serviceManager)
            )
        );

        return new InputFilterPluginManager($serviceManager);
    }

    protected function getV2InvalidPluginException(): string
    {
        return RuntimeException::class;
    }

    protected function getInstanceOf()
    {
        // InputFilterManager accepts multiple instance types
    }
}
