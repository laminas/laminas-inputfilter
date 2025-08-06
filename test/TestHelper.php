<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use Laminas\Validator\ValidatorPluginManager;
use LaminasTest\InputFilter\TestAsset\ValidatorStub;
use Psr\Container\ContainerInterface;
use ReflectionProperty;

final class TestHelper
{
    public static function createInputFilterFactory(): Factory
    {
        $serviceManager = new ServiceManager();

        $factory = new Factory(
            self::createFilterPluginManager($serviceManager),
            self::createValidatorPluginManager($serviceManager),
            new InputFilterPluginManager($serviceManager)
        );

        $serviceManager->setService(Factory::class, $factory);

        return $factory;
    }

    public static function createFilterPluginManager(ContainerInterface|null $container = null): FilterPluginManager
    {
        return new FilterPluginManager($container ?? new ServiceManager());
    }

    public static function createValidatorPluginManager(
        ContainerInterface|null $container = null
    ): ValidatorPluginManager {
        return new ValidatorPluginManager($container ?? new ServiceManager());
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
        array $messages = []
    ): ValidatorInterface {
        return new ValidatorStub($isValid, $value, $context, $messages);
    }

    public static function createValidatorChain(mixed $value = null, bool $isValid = true): ValidatorChain
    {
        $validatorChain = new ValidatorChain();
        $validatorChain->setPluginManager(self::createValidatorPluginManager());
        if ($value !== null) {
            $validatorChain->attach(self::createValidatorMock($isValid, $value));
        }

        return $validatorChain;
    }

    public static function createFilterChain(): FilterChain
    {
        $filterChain = new FilterChain();
        /** @psalm-suppress DeprecatedMethod removal will be done in Service Manager 4 upgrade */
        $filterChain->setPluginManager(self::createFilterPluginManager());
        return $filterChain;
    }

    public static function createFilterChainFixture(mixed $originalValue, mixed $filteredValue): FilterChain
    {
        $filterChain = new FilterChain();
        /** @psalm-suppress DeprecatedMethod removal will be done in Service Manager 4 upgrade */
        $filterChain->setPluginManager(self::createFilterPluginManager());

        $filterChain->attach(
            fn(mixed $value): mixed => $value === $originalValue ? $filteredValue : $value,
        );

        return $filterChain;
    }

    /** @param array<int, array<mixed, mixed>> $valueMap */
    public static function createFilterChainFixtureFromMap(array $valueMap): FilterChain
    {
        $filterChain = new FilterChain();
        /** @psalm-suppress DeprecatedMethod removal will be done in Service Manager 4 upgrade */
        $filterChain->setPluginManager(self::createFilterPluginManager());

        foreach ($valueMap as $values) {
            $filterChain->attach(
                fn(mixed $value): mixed => $value === $values[0] ? $values[1] : $value,
            );
        }

        return $filterChain;
    }
}
