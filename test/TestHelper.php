<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

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

    /**
     * @param array<string, string> $messages
     */
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
}
