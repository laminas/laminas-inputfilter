<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * @psalm-internal Laminas\InputFilter
 * @psalm-internal LaminasTest\InputFilter
 */
final class InputFilterFactory implements AbstractFactoryInterface
{
    /**
     * @param string $requestedName
     * @return object
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        /** @psalm-suppress InvalidStringClass */
        return new $requestedName($container->get(Factory::class));
    }

    /**
     * @param string $requestedName
     */
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        return match ($requestedName) {
            InputFilter::class,
            CollectionInputFilter::class,
            OptionalInputFilter::class => true,
            default => false,
        };
    }
}
