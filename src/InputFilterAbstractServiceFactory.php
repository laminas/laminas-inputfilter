<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterPluginManager;
use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorPluginManager;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;

/** @final */
class InputFilterAbstractServiceFactory implements AbstractFactoryInterface
{
    /** @var Factory|null */
    protected $factory;

    /** @param string $requestedName */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): InputFilterInterface {
        $allConfig = $container->get('config');
        $config    = $allConfig['input_filter_specs'][$requestedName];
        $factory   = $this->getInputFilterFactory($container);

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

    protected function getInputFilterFactory(ContainerInterface $container): Factory
    {
        if ($this->factory instanceof Factory) {
            return $this->factory;
        }

        $this->factory  = $container->get(Factory::class);
        $filterChain    = $this->factory->getDefaultFilterChain();
        $validatorChain = $this->factory->getDefaultValidatorChain();
        assert($filterChain instanceof FilterChain);
        assert($validatorChain instanceof ValidatorChain);

        $filterChain->setPluginManager($container->get(FilterPluginManager::class));
        $validatorChain->setPluginManager($container->get(ValidatorPluginManager::class));

        return $this->factory;
    }
}
