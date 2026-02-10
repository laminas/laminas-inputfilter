<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\Spec;

use Laminas\Filter\StringTrim;
use Laminas\Filter\ToNull;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Validator\Ip;
use Laminas\Validator\NotEmpty;

/** @psalm-import-type InputFilterSpecification from InputFilterInterface */
final readonly class BasicFilterAndValidate implements InputFilterTestSpecInterface
{
    public function __construct()
    {
    }

    /** @inheritDoc */
    public function spec(): array
    {
        /** @psalm-var InputFilterSpecification */
        return [
            'type' => InputFilter::class,
            'a'    => [
                'name'       => 'a',
                'required'   => true,
                'filters'    => [
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class],
                ],
                'validators' => [
                    ['name' => NotEmpty::class],
                    ['name' => Ip::class],
                ],
            ],
            'b'    => [
                'type' => InputFilter::class,
                'c'    => [
                    'name'       => 'c',
                    'required'   => true,
                    'filters'    => [
                        ['name' => StringTrim::class],
                        ['name' => ToNull::class],
                    ],
                    'validators' => [
                        ['name' => NotEmpty::class],
                        ['name' => Ip::class],
                    ],
                ],
                'd'    => [
                    'name'        => 'd',
                    'required'    => false,
                    'allow_empty' => true,
                    'filters'     => [
                        ['name' => StringTrim::class],
                        ['name' => ToNull::class],
                    ],
                    'validators'  => [],
                ],
            ],
        ];
    }

    /** @inheritDoc */
    public function expectations(): array
    {
        return [
            'Valid Payload'      => new Expectation(
                [
                    'a' => ' 123.123.123.123 ',
                    'b' => [
                        'c' => '1.1.1.1',
                        'd' => '',
                    ],
                ],
                true,
                [],
                [
                    'a',
                    'b.c',
                    'b.d',
                ],
                [
                    'a' => '123.123.123.123',
                    'b' => [
                        'c' => '1.1.1.1',
                        'd' => null,
                    ],
                ],
            ),
            'Empty Payload'      => new Expectation(
                [],
                false,
                [
                    'a',
                    'b.c',
                ],
                [
                    'b.d',
                ],
                [
                    'a' => null,
                    'b' => [
                        'c' => null,
                        'd' => null,
                    ],
                ],
            ),
            'Non-empty, Invalid' => new Expectation(
                [
                    'a' => 'fred',
                    'b' => [
                        'c' => 'wilma',
                        'd' => 'barney',
                    ],
                ],
                false,
                [
                    'a',
                    'b.c',
                ],
                [
                    'b.d',
                ],
                [
                    'a' => 'fred',
                    'b' => [
                        'c' => 'wilma',
                        'd' => 'barney',
                    ],
                ],
            ),
        ];
    }
}
