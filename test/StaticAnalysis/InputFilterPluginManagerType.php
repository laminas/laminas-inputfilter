<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\StaticAnalysis;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputInterface;

final class InputFilterPluginManagerType
{
    public function __construct(private InputFilterPluginManager $manager)
    {
    }

    public function getWillReturnAnInputOrInputFilterGivenAString(
        string $anyString,
    ): InputInterface|InputFilterInterface {
        return $this->manager->get($anyString);
    }

    public function getWithFQCNWillReturnTheObjectOfType(): InputFilterWithTemplatedValues
    {
        return $this->manager->get(InputFilterWithTemplatedValues::class);
    }

    public function getInvalidFQCNReturnsFallbackType(): InputInterface|InputFilterInterface
    {
        return $this->manager->get(self::class);
    }
}
