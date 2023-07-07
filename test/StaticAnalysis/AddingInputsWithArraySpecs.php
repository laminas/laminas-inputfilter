<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\StaticAnalysis;

use Laminas\Filter\StringTrim;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\NotEmpty;

/** @psalm-suppress UnusedClass */
final class AddingInputsWithArraySpecs extends InputFilter
{
    public function addsAnInputWithAnArraySpec(): void
    {
        $this->add([
            'name'       => 'input1',
            'required'   => true,
            'filters'    => [
                'trim' => [
                    'name' => StringTrim::class,
                ],
            ],
            'validators' => [
                'notEmpty' => [
                    'name' => NotEmpty::class,
                ],
            ],
        ]);
    }

    public function addsAnInputWithNonStandardKeys(): void
    {
        $this->add([
            'name'       => 'input1',
            'required'   => true,
            'custom-key' => 'something',
        ]);
    }
}
