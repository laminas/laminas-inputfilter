<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\ServiceManager;

/**
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @final
 */
class Module
{
    /**
     * Return default laminas-inputfilter configuration for laminas-mvc applications.
     *
     * @return array<string, mixed>
     * @psalm-return array{
     *     service_manager: ServiceManagerConfiguration,
     *     input_filters: ServiceManagerConfiguration,
     * }
     */
    public function getConfig(): array
    {
        $provider = new ConfigProvider();

        return [
            'service_manager' => $provider->getDependencyConfig(),
            'input_filters'   => $provider->getInputFilterConfig(),
        ];
    }
}
