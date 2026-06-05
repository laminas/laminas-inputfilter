<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\StaticAnalysis;

final readonly class ValidationResultValuesHavePreciseInference
{
    public function __construct(private NestedInputFilterWithTemplatedValues $inputFilter)
    {
    }

    /** @return non-empty-string */
    public function fetchFromNestedResult(array $input): string
    {
        $result = $this->inputFilter->validate($input);

        return $result->value()['nested']['someString'];
    }
}
