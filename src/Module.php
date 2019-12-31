<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\InputFilter;

class Module
{
    /**
     * Return default laminas-inputfilter configuration for laminas-mvc applications.
     */
    public function getConfig()
    {
        $provider = new ConfigProvider();

        return [
            'service_manager' => $provider->getDependencyConfig(),
        ];
    }

    /**
     * Register a specification for the InputFilterManager with the ServiceListener.
     *
     * @param \Laminas\ModuleManager\ModuleEvent
     * @return void
     */
    public function init($event)
    {
        $container = $event->getParam('ServiceManager');
        $serviceListener = $container->get('ServiceListener');

        $serviceListener->addServiceManager(
            'InputFilterManager',
            'input_filters',
            'Laminas\ModuleManager\Feature\InputFilterProviderInterface',
            'getInputFilterConfig'
        );
    }
}
