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
    public function __invoke()
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
    public function getDependencyConfig()
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
     * @return ServiceManagerConfigurationType
     */
    public function getInputFilterConfig()
    {
        return [
            'abstract_factories' => [
                InputFilterAbstractServiceFactory::class,
            ],
        ];
    }
}
