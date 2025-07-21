<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterPluginManager;
use Laminas\Validator\ValidatorPluginManager;
use Psr\Container\ContainerInterface;

/**
 * @psalm-internal Laminas\InputFilter
 * @psalm-internal LaminasTest\InputFilter
 */
final class FactoryFactory
{
    public function __invoke(ContainerInterface $container): Factory
    {
        return new Factory(
            $container->get(FilterPluginManager::class),
            $container->get(ValidatorPluginManager::class),
            $container->get(InputFilterPluginManager::class)
        );
    }
}
