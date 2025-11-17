<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\ServiceManager;

/**
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @psalm-internal Laminas\InputFilter
 * @psalm-internal LaminasTest\InputFilter
 */
final readonly class ConfigProvider
{
    /**
     * Return configuration for this component.
     *
     * @return array{
     *     dependencies: ServiceManagerConfiguration,
     *     input_filters: ServiceManagerConfiguration,
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies'  => $this->getDependencyConfig(),
            'input_filters' => $this->getInputFilterConfig(),
        ];
    }

    /**
     * Return dependency mappings for this component.
     *
     * @psalm-return ServiceManagerConfiguration
     */
    public function getDependencyConfig(): array
    {
        return [
            'aliases'   => [
                'InputFilterManager' => InputFilterPluginManager::class,
            ],
            'factories' => [
                Factory::class                  => FactoryFactory::class,
                InputFilterPluginManager::class => InputFilterPluginManagerFactory::class,
            ],
        ];
    }

    /**
     * Get input filter configuration
     *
     * @psalm-return ServiceManagerConfiguration
     */
    public function getInputFilterConfig(): array
    {
        return [
            'abstract_factories' => [
                InputFilterAbstractServiceFactory::class,
            ],
        ];
    }
}
