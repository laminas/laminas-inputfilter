<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

use function is_array;

/**
 * @psalm-internal Laminas\InputFilter
 * @psalm-internal LaminasTest\InputFilter
 */
final class InputFilterAbstractServiceFactory implements AbstractFactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null
    ): InputFilterInterface {
        $allConfig = $container->get('config');
        $config    = $allConfig['input_filter_specs'][$requestedName];
        $factory   = $container->get(Factory::class);

        return $factory->createInputFilter($config);
    }

    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        if (! $container->has('config')) {
            return false;
        }

        $config = $container->get('config');
        if (
            ! isset($config['input_filter_specs'][$requestedName])
            || ! is_array($config['input_filter_specs'][$requestedName])
        ) {
            return false;
        }

        return true;
    }
}
