<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\StaticAnalysis;

use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputInterface;

/** @psalm-suppress PossiblyUnusedMethod */
final readonly class InputFilterPluginManagerType
{
    public function __construct(private InputFilterPluginManager $manager)
    {
    }

    public function getWillReturnMixedGivenAString(
        string $anyString,
    ): mixed {
        return $this->manager->get($anyString);
    }

    public function getWithFQCNWillReturnTheObjectOfType(): InputFilterWithTemplatedValues
    {
        return $this->manager->get(InputFilterWithTemplatedValues::class);
    }

    public function getInvalidFQCNReturnsGivenFQCN(): self
    {
        return $this->manager->get(self::class);
    }

    public function getInput(): InputInterface
    {
        return $this->manager->get(Input::class);
    }

    public function getInputFilter(): InputFilterInterface
    {
        return $this->manager->get(InputFilter::class);
    }
}
