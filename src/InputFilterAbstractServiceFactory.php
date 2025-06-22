<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Psr\Container\ContainerInterface;

use function is_array;

/** @final */
class InputFilterAbstractServiceFactory implements AbstractFactoryInterface
{
    /** @param string $requestedName */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): InputFilterInterface {
        $allConfig = $container->get('config');
        $config    = $allConfig['input_filter_specs'][$requestedName];
        $factory   = $container->get(Factory::class);

        return $factory->createInputFilter($config);
    }

    /** @param string $requestedName */
    public function canCreate(ContainerInterface $container, $requestedName): bool
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

    /**
     * Determine if we can create a service with name (v2)
     *
     * @deprecated This library is no longer compatible with Service manager V2 and this method will be dropped in the
     *             next major release.
     *
     * @param string $name
     * @param string $requestedName
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        return $this->canCreate($serviceLocator, $requestedName);
    }

    /**
     * Create the requested service (v2)
     *
     * @deprecated This library is no longer compatible with Service manager V2 and this method will be dropped in the
     *             next major release.
     *
     * @param string $name
     * @param string $requestedName
     */
    public function createServiceWithName(
        ServiceLocatorInterface $serviceLocator,
        $name,
        $requestedName
    ): InputFilterInterface {
        return $this($serviceLocator, $requestedName);
    }
}
