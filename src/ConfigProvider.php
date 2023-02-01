<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\ConfigInterface;

/** @psalm-import-type ServiceManagerConfigurationType from ConfigInterface */
class ConfigProvider
{
    /**
     * Return configuration for this component.
     *
     * @return array{
     *     dependencies: ServiceManagerConfigurationType,
     *     input_filters: ServiceManagerConfigurationType,
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
     * @psalm-return ServiceManagerConfigurationType
     * @return array
     */
    public function getDependencyConfig(): array
    {
        return [
            'aliases'   => [
                'InputFilterManager' => InputFilterPluginManager::class,

                // Legacy Zend Framework aliases
                'Zend\InputFilter\InputFilterPluginManager' => InputFilterPluginManager::class,
            ],
            'factories' => [
                InputFilterPluginManager::class => InputFilterPluginManagerFactory::class,
            ],
        ];
    }

    /**
     * Get input filter configuration
     *
     * @psalm-return ServiceManagerConfigurationType
     * @return array
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
