<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Laminas\InputFilter\Factory;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

final class FooAbstractFactory implements AbstractFactoryInterface
{
    /** @param string $requestedName */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Foo
    {
        return new Foo($container->get(Factory::class));
    }

    /** @param string $requestedName */
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        if ($requestedName === 'foo') {
            return true;
        }

        return false;
    }
}
