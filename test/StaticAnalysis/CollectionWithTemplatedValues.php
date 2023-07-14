<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\StaticAnalysis;

use Laminas\InputFilter\CollectionInputFilter;

/**
 * @psalm-import-type FilteredValues from InputFilterWithTemplatedValues as CollectionShape
 * @psalm-type FilteredValues = array<array-key, CollectionShape>
 * @extends CollectionInputFilter<FilteredValues>
 */
final class CollectionWithTemplatedValues extends CollectionInputFilter
{
    public function init(): void
    {
        $this->setInputFilter(new InputFilterWithTemplatedValues());
    }
}
