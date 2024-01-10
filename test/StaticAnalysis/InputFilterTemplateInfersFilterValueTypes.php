<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\StaticAnalysis;

use function assert;
use function count;
use function reset;

final class InputFilterTemplateInfersFilterValueTypes
{
    public function __construct(
        private readonly InputFilterWithTemplatedValues $inputFilter,
        private readonly NestedInputFilterWithTemplatedValues $nestedFilter,
        private readonly CollectionWithTemplatedValues $collection,
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

    /** @param array<array-key, mixed> $input */
    public function retrieveCollectionValue(array $input): int
    {
        $this->collection->setData($input);
        assert($this->collection->isValid());

        $values = $this->collection->getValues();
        assert(count($values) >= 1);

        $first = reset($values);

        return $first['someInt'];
    }
}
