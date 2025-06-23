<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;

final class FactoryTestHelper
{
    public static function createInputFilterFactory(): Factory
    {
        $serviceManager = new ServiceManager();

        $factory = new Factory(
            new FilterPluginManager($serviceManager),
            new ValidatorPluginManager($serviceManager),
            new InputFilterPluginManager($serviceManager)
        );

        $serviceManager->setService(Factory::class, $factory);

        return $factory;
    }
}
