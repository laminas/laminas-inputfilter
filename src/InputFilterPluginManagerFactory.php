<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use ArrayAccess;
use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;
use Psr\Container\ContainerInterface as PsrContainer;

use function assert;
use function is_array;

/**
 * @link ServiceManager
 *
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
class InputFilterPluginManagerFactory implements FactoryInterface
{
    /**
     * laminas-servicemanager v2 support for invocation options.
     *
     * @var null|ServiceManagerConfiguration
     */
    protected $creationOptions;

    /**
     * @param ContainerInterface|PsrContainer  $container
     * @param string|null                      $name
     * @param ServiceManagerConfiguration|null $options
     * @return InputFilterPluginManager
     * @psalm-suppress MoreSpecificImplementedParamType,MismatchingDocblockParamType
     */
    public function __invoke(ContainerInterface $container, $name = null, ?array $options = null)
    {
        $pluginManager = new InputFilterPluginManager($container, $options ?: []);

        // If this is in a laminas-mvc application, the ServiceListener will inject
        // merged configuration during bootstrap.
        if ($container->has('ServiceListener')) {
            return $pluginManager;
        }

        // If we do not have a config service, nothing more to do
        if (! $container->has('config')) {
            return $pluginManager;
        }

        $config = $container->get('config');
        assert(is_array($config) || $config instanceof ArrayAccess);

        // If we do not have input_filters configuration, nothing more to do
        if (! isset($config['input_filters']) || ! is_array($config['input_filters'])) {
            return $pluginManager;
        }

        /** @psalm-var ServiceManagerConfiguration $config['input_filters'] */

        // Wire service configuration for input_filters
        (new Config($config['input_filters']))->configureServiceManager($pluginManager);

        return $pluginManager;
    }

    /**
     * @param string|null $name
     * @param string|null $requestedName
     * @return InputFilterPluginManager
     * @psalm-suppress MoreSpecificImplementedParamType,MismatchingDocblockParamType
     */
    public function createService(ServiceLocatorInterface $container, $name = null, $requestedName = null)
    {
        return $this($container, $requestedName ?: InputFilterPluginManager::class, $this->creationOptions);
    }

    /**
     * laminas-servicemanager v2 support for invocation options.
     *
     * @param ServiceManagerConfiguration $options
     * @return void
     */
    public function setCreationOptions(array $options)
    {
        $this->creationOptions = $options;
    }
}
