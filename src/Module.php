<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ModuleManager\ModuleManager;
use Laminas\ServiceManager\ConfigInterface;

/** @psalm-import-type ServiceManagerConfigurationType from ConfigInterface */
class Module
{
    /**
     * Return default laminas-inputfilter configuration for laminas-mvc applications.
     *
     * @return array<string, mixed>
     * @psalm-return array{
     *     service_manager: ServiceManagerConfigurationType,
     *     input_filters: ServiceManagerConfigurationType,
     * }
     */
    public function getConfig()
    {
        $provider = new ConfigProvider();

        return [
            'service_manager' => $provider->getDependencyConfig(),
            'input_filters'   => $provider->getInputFilterConfig(),
        ];
    }

    /**
     * Register a specification for the InputFilterManager with the ServiceListener.
     *
     * @param ModuleManager $moduleManager
     * @return void
     */
    public function init($moduleManager)
    {
        $event           = $moduleManager->getEvent();
        $container       = $event->getParam('ServiceManager');
        $serviceListener = $container->get('ServiceListener');

        $serviceListener->addServiceManager(
            'InputFilterManager',
            'input_filters',
            'Laminas\ModuleManager\Feature\InputFilterProviderInterface',
            'getInputFilterConfig'
        );
    }
}
