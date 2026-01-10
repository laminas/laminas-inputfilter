<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\ConfigProvider;
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use PHPUnit\Framework\TestCase;

final class ConfigProviderTest extends TestCase
{
    public function testProvidesExpectedInputFilterConfiguration(): void
    {
        $provider = new ConfigProvider();

        $expected = [
            'abstract_factories' => [
                InputFilterAbstractServiceFactory::class,
            ],
        ];

        self::assertEquals($expected, $provider->getInputFilterConfig());
    }

    public function testInvocationProvidesDependencyConfiguration(): void
    {
        $provider = new ConfigProvider();

        $expected = [
            'dependencies'  => $provider->getDependencyConfig(),
            'input_filters' => $provider->getInputFilterConfig(),
        ];
        self::assertEquals($expected, $provider->__invoke());
    }
}
