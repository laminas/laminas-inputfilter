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

final class TestHelper
{
    public static function createInputFilterFactory(): Factory
    {
        $serviceManager = new ServiceManager();

        $factory = new Factory(
            new FilterPluginManager($serviceManager),
            new ValidatorPluginManager($serviceManager),
            new InputFilterPluginManager($serviceManager)
        );

        $serviceManager->setService(Factory::class, $factory);

        return $factory;
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

    public static function createValidatorChain(mixed $value, bool $isValid): ValidatorChain
    {
        return (new ValidatorChain())->attach(self::createValidatorMock($isValid, $value));
    }

    public static function createFilterChain(): FilterChain
    {
        return new FilterChain();
    }

    public static function createFilterChainFixture(mixed $originalValue, mixed $filteredValue): FilterChain
    {
        $filterChain = new FilterChain();

        $filterChain->attach(
            fn(mixed $value): mixed => $value === $originalValue ? $filteredValue : $value,
        );

        return $filterChain;
    }

    /** @param array<int, array<mixed, mixed>> $valueMap */
    public static function createFilterChainFixtureFromMap(array $valueMap): FilterChain
    {
        $filterChain = new FilterChain();

        foreach ($valueMap as $values) {
            $filterChain->attach(
                fn(mixed $value): mixed => $value === $values[0] ? $values[1] : $value,
            );
        }

        return $filterChain;
    }
}
