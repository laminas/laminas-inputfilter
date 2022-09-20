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

class InputFilterAbstractServiceFactory implements AbstractFactoryInterface
{
    /** @var Factory|null */
    protected $factory;

    /**
     * @param string                  $rName
     * @param array                   $options
     * @return InputFilterInterface
     */
    public function __invoke(ContainerInterface $services, $rName, ?array $options = null)
    {
        $allConfig = $services->get('config');
        $config    = $allConfig['input_filter_specs'][$rName];
        $factory   = $this->getInputFilterFactory($services);

        return $factory->createInputFilter($config);
    }

    /**
     * @param string $rName
     * @return bool
     */
    public function canCreate(ContainerInterface $services, $rName)
    {
        if (! $services->has('config')) {
            return false;
        }

        $config = $services->get('config');
        if (
            ! isset($config['input_filter_specs'][$rName])
            || ! is_array($config['input_filter_specs'][$rName])
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
    public function canCreateServiceWithName(ServiceLocatorInterface $container, $name, $requestedName)
    {
        return $this->canCreate($container, $requestedName);
    }

    /**
     * Create the requested service (v2)
     *
     * @deprecated This library is no longer compatible with Service manager V2 and this method will be dropped in the
     *             next major release.
     *
     * @param string                  $cName
     * @param string                  $rName
     * @return InputFilterInterface
     */
    public function createServiceWithName(ServiceLocatorInterface $container, $cName, $rName)
    {
        return $this($container, $rName);
    }

    /**
     * @return Factory
     */
    protected function getInputFilterFactory(ContainerInterface $container)
    {
        if ($this->factory instanceof Factory) {
            return $this->factory;
        }

        $this->factory  = new Factory();
        $filterChain    = $this->factory->getDefaultFilterChain();
        $validatorChain = $this->factory->getDefaultValidatorChain();
        assert($filterChain instanceof FilterChain);
        assert($validatorChain instanceof ValidatorChain);

        $filterChain->setPluginManager($this->getFilterPluginManager($container));
        $validatorChain->setPluginManager($this->getValidatorPluginManager($container));

        $this->factory->setInputFilterManager($container->get(InputFilterPluginManager::class));

        return $this->factory;
    }

    /**
     * @return FilterPluginManager
     */
    protected function getFilterPluginManager(ContainerInterface $container)
    {
        if ($container->has(FilterPluginManager::class)) {
            return $container->get(FilterPluginManager::class);
        }

        return new FilterPluginManager($container);
    }

    /**
     * @return ValidatorPluginManager
     */
    protected function getValidatorPluginManager(ContainerInterface $container)
    {
        if ($container->has(ValidatorPluginManager::class)) {
            return $container->get(ValidatorPluginManager::class);
        }

        return new ValidatorPluginManager($container);
    }
}
