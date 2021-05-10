<?php

namespace LaminasTest\InputFilter\TestAsset;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class FooAbstractFactory implements AbstractFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        return new Foo();
    }

    public function canCreate(ContainerInterface $container, $name)
    {
        if ($name == 'foo') {
            return true;
        }
    }

    public function canCreateServiceWithName(ServiceLocatorInterface $container, $name, $requestedName)
    {
        return $this->canCreate($container, $requestedName ?: $name);
    }

    public function createServiceWithName(ServiceLocatorInterface $container, $name, $requestedName)
    {
        return $this($container, $requestedName ?: $name);
    }
}
