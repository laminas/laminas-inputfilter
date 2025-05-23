<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;

/**
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
final class InputFilterPluginManagerFactory
{
    public function __invoke(ContainerInterface $container): InputFilterPluginManager
    {
        $config = $container->has('config') ? $container->get('config') : [];
        assert(is_array($config));

        /** @psalm-var ServiceManagerConfiguration $inputFilters */
        $inputFilters = isset($config['input_filters']) && is_array($config['input_filters'])
            ? $config['input_filters']
            : [];

        return new InputFilterPluginManager($container, $inputFilters);
    }
}
