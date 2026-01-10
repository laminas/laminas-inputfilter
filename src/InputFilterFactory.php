<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

use function assert;
use function is_a;

/**
 * @psalm-internal Laminas\InputFilter
 * @psalm-internal LaminasTest\InputFilter
 */
final class InputFilterFactory implements AbstractFactoryInterface
{
    /** @inheritDoc */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): InputFilterInterface {
        assert(is_a($requestedName, InputFilterInterface::class, true));

        return new $requestedName($container->get(Factory::class));
    }

    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        return match ($requestedName) {
            InputFilter::class,
            CollectionInputFilter::class,
            OptionalInputFilter::class => true,
            default => false,
        };
    }
}
