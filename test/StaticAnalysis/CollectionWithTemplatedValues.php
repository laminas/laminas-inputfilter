<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\StaticAnalysis;

use Laminas\InputFilter\CollectionInputFilter;

/**
 * @psalm-import-type FilteredValues from InputFilterWithTemplatedValues as CollectionShape
 * @extends CollectionInputFilter<CollectionShape>
 */
final class CollectionWithTemplatedValues extends CollectionInputFilter
{
    public function init(): void
    {
        $this->setInputFilter(new InputFilterWithTemplatedValues($this->factory));
    }
}
