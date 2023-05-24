<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\StaticAnalysis;

use function assert;

final class InputFilterTemplateInfersFilterValueTypes
{
    public function __construct(
        private readonly InputFilterWithTemplatedValues $inputFilter,
        private readonly NestedInputFilterWithTemplatedValues $nestedFilter,
    ) {
    }

    /** @param array<array-key, mixed> $input */
    public function retrieveInteger(array $input): int
    {
        $this->inputFilter->setData($input);
        assert($this->inputFilter->isValid());

        return $this->inputFilter->getValues()['someInt'];
    }

    /**
     * @param array<array-key, mixed> $input
     * @return non-empty-string
     */
    public function retrieveString(array $input): string
    {
        $this->inputFilter->setData($input);
        assert($this->inputFilter->isValid());

        return $this->inputFilter->getValues()['someString'];
    }

    /**
     * @param array<array-key, mixed> $input
     * @return non-empty-string
     */
    public function retrieveNestedString(array $input): string
    {
        $this->nestedFilter->setData($input);
        assert($this->nestedFilter->isValid());

        return $this->nestedFilter->getValues()['nested']['someString'];
    }
}
