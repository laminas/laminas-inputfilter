<?php

namespace Laminas\InputFilter;

use Laminas\ModuleManager\ModuleManager;

class Module
{
    /**
     * Return default laminas-inputfilter configuration for laminas-mvc applications.
     *
     * @return array
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
