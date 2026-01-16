<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasTest\InputFilter\TestHelper;
use Psr\Container\ContainerInterface;

use function assert;
use function is_int;
use function is_string;
use function uniqid;

final readonly class CustomInputFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): CustomInput {
        $options ??= [];

        $name = $options['name'] ?? uniqid();
        assert((is_string($name) && $name !== '') || is_int($name));
        /** @psalm-var non-empty-string|int $name */

        return new CustomInput(
            TestHelper::createFilterChain(),
            TestHelper::createValidatorChain(),
            $name,
        );
    }
}
