<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\ConfigProvider;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use Laminas\Validator\ValidatorPluginManager;
use LaminasTest\InputFilter\TestAsset\ValidatorStub;
use ReflectionProperty;

use function array_replace_recursive;

/** @psalm-import-type ServiceManagerConfiguration from ServiceManager */
final class TestHelper
{
    public static function getContainer(array $config = []): ServiceManager
    {
        $config = array_replace_recursive(
            (new \Laminas\Filter\ConfigProvider())->__invoke(),
            (new \Laminas\Validator\ConfigProvider())->__invoke(),
            (new ConfigProvider())->__invoke(),
            $config,
        );

        /** @psalm-var ServiceManagerConfiguration $deps */
        $deps                       = $config['dependencies'] ?? [];
        $deps['services']         ??= [];
        $deps['services']['config'] = $config;

        /** @psalm-var ServiceManagerConfiguration $deps */

        return new ServiceManager($deps);
    }

    public static function createInputFilterFactory(ServiceManager|null $container = null): Factory
    {
        $container ??= self::getContainer();

        if ($container->has(Factory::class)) {
            return $container->get(Factory::class);
        }

        $factory = new Factory(
            self::createFilterPluginManager($container),
            self::createValidatorPluginManager($container),
            new InputFilterPluginManager($container),
        );

        $container->setService(Factory::class, $factory);

        return $factory;
    }

    public static function createFilterPluginManager(ServiceManager|null $container = null): FilterPluginManager
    {
        $container ??= self::getContainer();
        if ($container->has(FilterPluginManager::class)) {
            return $container->get(FilterPluginManager::class);
        }

        return new FilterPluginManager($container);
    }

    public static function createValidatorPluginManager(
        ServiceManager|null $container = null,
    ): ValidatorPluginManager {
        $container ??= self::getContainer();
        if ($container->has(ValidatorPluginManager::class)) {
            return $container->get(ValidatorPluginManager::class);
        }

        return new ValidatorPluginManager($container);
    }

    public static function getFilterPluginManagerFromFilterChain(FilterChain $filterChain): mixed
    {
        return (new ReflectionProperty(FilterChain::class, 'plugins'))->getValue($filterChain);
    }

    /** @param array<string, string> $messages */
    public static function createValidatorMock(
        ?bool $isValid,
        mixed $value = 'not-set',
        ?array $context = null,
        array $messages = [],
    ): ValidatorInterface {
        return new ValidatorStub($isValid, $value, $context, $messages);
    }

    public static function createValidatorChain(mixed $value = null, bool $isValid = true): ValidatorChain
    {
        $validatorChain = new ValidatorChain(self::createValidatorPluginManager());
        if ($value !== null) {
            $validatorChain->attach(self::createValidatorMock($isValid, $value));
        }

        return $validatorChain;
    }

    public static function createFilterChain(): FilterChain
    {
        return new FilterChain(self::createFilterPluginManager());
    }

    public static function createFilterChainFixture(mixed $originalValue, mixed $filteredValue): FilterChain
    {
        $filterChain = new FilterChain(self::createFilterPluginManager());

        $filterChain->attach(
            fn(mixed $value): mixed => $value === $originalValue ? $filteredValue : $value,
        );

        return $filterChain;
    }

    /** @param array<int, array<mixed, mixed>> $valueMap */
    public static function createFilterChainFixtureFromMap(array $valueMap): FilterChain
    {
        $filterChain = new FilterChain(self::createFilterPluginManager());

        foreach ($valueMap as $values) {
            $filterChain->attach(
                fn(mixed $value): mixed => $value === $values[0] ? $values[1] : $value,
            );
        }

        return $filterChain;
    }
}
