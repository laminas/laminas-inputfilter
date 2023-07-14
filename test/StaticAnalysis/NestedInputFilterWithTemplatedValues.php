<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\StaticAnalysis;

use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\Filter\ToNull;
use Laminas\InputFilter\InputFilter;

/**
 * @psalm-type FilteredValues = array{
 *     someInt: int,
 *     someString: non-empty-string,
 *     nested: array {
 *         someInt: int,
 *         someString: non-empty-string,
 *     }
 * }
 * @extends InputFilter<FilteredValues>
 */
final class NestedInputFilterWithTemplatedValues extends InputFilter
{
    public function init(): void
    {
        $this->add([
            'name'     => 'someInt',
            'required' => true,
            'filters'  => [
                'toNull' => ['name' => ToNull::class],
                'toInt'  => ['name' => ToInt::class],
            ],
        ]);

        $this->add([
            'name'     => 'someString',
            'required' => true,
            'filters'  => [
                'trim'   => ['name' => StringTrim::class],
                'toNull' => ['name' => ToNull::class],
            ],
        ]);

        $this->add([
            'name' => 'nested',
            'type' => InputFilterWithTemplatedValues::class,
        ]);
    }
}
