<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;

/**
 * @link ServiceManager
 *
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @psalm-internal Laminas\InputFilter
 * @psalm-internal LaminasTest\InputFilter
 */
final class InputFilterPluginManagerFactory implements FactoryInterface
{
    /** @param string|null $requestedName */
    public function __invoke(
        ContainerInterface $container,
        $requestedName = null,
        ?array $options = null
    ): InputFilterPluginManager {
        $pluginManager = new InputFilterPluginManager($container, $options ?? []);

        // If we do not have a config service, nothing more to do
        if (! $container->has('config')) {
            return $pluginManager;
        }

        $config = $container->get('config');
        assert(is_array($config));

        // If we do not have input_filters configuration, nothing more to do
        if (! isset($config['input_filters']) || ! is_array($config['input_filters'])) {
            return $pluginManager;
        }

        /** @psalm-var ServiceManagerConfiguration $config['input_filters'] */
        $pluginManager->configure($config['input_filters']);

        return $pluginManager;
    }
}
