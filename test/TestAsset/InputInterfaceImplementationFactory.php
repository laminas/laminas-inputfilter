<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorPluginManager;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;
use function uniqid;

/** @psalm-import-type InputSpecification from InputFilterInterface */
final readonly class InputInterfaceImplementationFactory implements FactoryInterface
{
    /**
     * @param InputSpecification|null $options
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): InputInterfaceImplementation {
        $options ??= [];

        $validators    = $container->get(ValidatorPluginManager::class);
        $validatorSpec = $options['validators'] ?? [];
        assert(is_array($validatorSpec));
        $validatorChain = $validators->build(ValidatorChain::class, $validatorSpec);
        $filters        = $container->get(FilterPluginManager::class);
        $filterChain    = $filters->build(FilterChain::class, ['filters' => $options['filters'] ?? []]);

        return new InputInterfaceImplementation(
            $filterChain,
            $validatorChain,
            $options['name'] ?? uniqid(),
            $options,
        );
    }
}
