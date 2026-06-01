<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\Spec;

use Laminas\Filter\StringTrim;
use Laminas\Filter\ToNull;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Validator\Ip;
use Laminas\Validator\NotEmpty;

/** @psalm-import-type InputFilterSpecification from InputFilterInterface */
final readonly class BasicCollections implements InputFilterTestSpecInterface
{
    public function __construct()
    {
    }

    /** @inheritDoc */
    public function spec(): array
    {
        /** @psalm-var InputFilterSpecification */
        return [
            'type'    => InputFilter::class,
            'collect' => [
                'type'         => CollectionInputFilter::class,
                'required'     => true,
                'count'        => 2,
                'input_filter' => [
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
                        'name'       => 'b',
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
                ],
            ],
        ];
    }

    /**
     * @inheritDoc
     * phpcs:disable WebimpressCodingStandard.Arrays.DoubleArrow.SpacesBefore
     */
    public function expectations(): array
    {
        return [
            'Valid Payload' => new Expectation(
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => '1.1.1.4',
                            'b' => '1.1.1.5',
                            'c' => '1.1.1.6',
                        ],
                    ],
                ],
                true,
                [],
                [
                    'collect.0.a',
                    'collect.0.b',
                    'collect.0.c',
                    'collect.1.a',
                    'collect.1.b',
                    'collect.1.c',
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => '1.1.1.4',
                            'b' => '1.1.1.5',
                            'c' => '1.1.1.6',
                        ],
                    ],
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => '1.1.1.4',
                            'b' => '1.1.1.5',
                            'c' => '1.1.1.6',
                        ],
                    ],
                ],
            ),
            'Not enough items, but otherwise valid' => new Expectation(
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                    ],
                ],
                false,
                [
                    'collect.1.a',
                    'collect.1.b',
                    'collect.1.c',
                ],
                [
                    'collect.0.a',
                    'collect.0.b',
                    'collect.0.c',
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => null,
                            'b' => null,
                            'c' => null,
                        ],
                    ],
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => null,
                            'b' => null,
                            'c' => null,
                        ],
                    ],
                ],
            ),
            'Not enough items, also invalid values' => new Expectation(
                [
                    'collect' => [
                        0 => [
                            'a' => 'Fred',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                    ],
                ],
                false,
                [
                    'collect.0.a',
                    'collect.1.a',
                    'collect.1.b',
                    'collect.1.c',
                ],
                [
                    'collect.0.b',
                    'collect.0.c',
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => 'Fred',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => null,
                            'b' => null,
                            'c' => null,
                        ],
                    ],
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => 'Fred',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => null,
                            'b' => null,
                            'c' => null,
                        ],
                    ],
                ],
            ),
            'More than minimum count, but all valid' => new Expectation(
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => '1.1.1.4',
                            'b' => '1.1.1.5',
                            'c' => '1.1.1.6',
                        ],
                        2 => [
                            'a' => '1.1.1.7',
                            'b' => '1.1.1.8',
                            'c' => '1.1.1.9',
                        ],
                    ],
                ],
                true,
                [],
                [
                    'collect.0.a',
                    'collect.0.b',
                    'collect.0.c',
                    'collect.1.a',
                    'collect.1.b',
                    'collect.1.c',
                    'collect.2.a',
                    'collect.2.b',
                    'collect.2.c',
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => '1.1.1.4',
                            'b' => '1.1.1.5',
                            'c' => '1.1.1.6',
                        ],
                        2 => [
                            'a' => '1.1.1.7',
                            'b' => '1.1.1.8',
                            'c' => '1.1.1.9',
                        ],
                    ],
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => '1.1.1.4',
                            'b' => '1.1.1.5',
                            'c' => '1.1.1.6',
                        ],
                        2 => [
                            'a' => '1.1.1.7',
                            'b' => '1.1.1.8',
                            'c' => '1.1.1.9',
                        ],
                    ],
                ],
            ),
            'More than minimum count, 3rd item invalid' => new Expectation(
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => '1.1.1.4',
                            'b' => '1.1.1.5',
                            'c' => '1.1.1.6',
                        ],
                        2 => [
                            'a' => '1.1.1.7',
                            'b' => 'Granny',
                            'c' => '1.1.1.9',
                        ],
                    ],
                ],
                false,
                [
                    'collect.2.b',
                ],
                [
                    'collect.0.a',
                    'collect.0.b',
                    'collect.0.c',
                    'collect.1.a',
                    'collect.1.b',
                    'collect.1.c',
                    'collect.2.a',
                    'collect.2.c',
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => '1.1.1.4',
                            'b' => '1.1.1.5',
                            'c' => '1.1.1.6',
                        ],
                        2 => [
                            'a' => '1.1.1.7',
                            'b' => 'Granny',
                            'c' => '1.1.1.9',
                        ],
                    ],
                ],
                [
                    'collect' => [
                        0 => [
                            'a' => '1.1.1.1',
                            'b' => '1.1.1.2',
                            'c' => '1.1.1.3',
                        ],
                        1 => [
                            'a' => '1.1.1.4',
                            'b' => '1.1.1.5',
                            'c' => '1.1.1.6',
                        ],
                        2 => [
                            'a' => '1.1.1.7',
                            'b' => 'Granny',
                            'c' => '1.1.1.9',
                        ],
                    ],
                ],
            ),
        ];
    }
}
