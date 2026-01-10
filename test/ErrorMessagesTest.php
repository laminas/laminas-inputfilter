<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\ErrorMessages;
use Laminas\InputFilter\Exception\BadMethodCallException;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;
use function json_decode;
use function json_encode;

final class ErrorMessagesTest extends TestCase
{
    public function testIterationOverNestedSets(): void
    {
        $errors = new ErrorMessages(
            [
                'foo'    => 'Error 1',
                'bar'    => 'Error 2',
                'level2' => new ErrorMessages(
                    [
                        'baz'    => 'Error 3',
                        'level3' => new ErrorMessages([
                            'bat' => 'Error 4',
                        ]),
                    ],
                ),
            ],
        );

        self::assertSame([
            'foo'    => 'Error 1',
            'bar'    => 'Error 2',
            'level2' => [
                'baz'    => 'Error 3',
                'level3' => [
                    'bat' => 'Error 4',
                ],
            ],
        ], $errors->toArray());
    }

    public function testCountOfNestedEmptySetsIsZero(): void
    {
        $errors = new ErrorMessages([
            'foo'  => new ErrorMessages([
                'bar' => new ErrorMessages([]),
            ]),
            'bing' => new ErrorMessages([
                'bong' => new ErrorMessages([]),
            ]),
        ]);

        self::assertCount(0, $errors);
    }

    public function testCountIsCorrect(): void
    {
        $errors = new ErrorMessages([
            'foo'  => new ErrorMessages([
                'bar' => new ErrorMessages([
                    'baz' => 'Bad News',
                ]),
            ]),
            'bing' => new ErrorMessages([
                'bong' => new ErrorMessages([
                    'bung' => 'More bad news',
                ]),
            ]),
        ]);

        self::assertCount(2, $errors);
    }

    public function testArrayAccessCannotBeAbusedToAddErrors(): void
    {
        $errors = new ErrorMessages([]);
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Error messages are immutable');

        $errors['foo'] = 'bar';
    }

    public function testArrayAccessCannotBeAbusedToRemoveErrors(): void
    {
        $errors = new ErrorMessages([
            'foo' => 'bar',
        ]);
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Error messages are immutable');

        unset($errors['foo']);
    }

    public function testArrayAccessImplementation(): void
    {
        $nested = new ErrorMessages([
            'bar' => 'bat',
        ]);
        $errors = new ErrorMessages([
            'foo' => 'bar',
            'baz' => $nested,
        ]);

        self::assertTrue(isset($errors['foo']));
        self::assertFalse(isset($errors['bar']));

        self::assertSame('bar', $errors['foo']);
        self::assertInstanceOf(ErrorMessages::class, $errors['baz']);
        self::assertSame('bat', $errors['baz']['bar']);
    }

    public function testJsonEncodeProducesANestedArray(): void
    {
        $nested = new ErrorMessages([
            'bar' => 'bat',
        ]);
        $errors = new ErrorMessages([
            'foo' => 'bar',
            'baz' => $nested,
        ]);

        $expect = [
            'foo' => 'bar',
            'baz' => [
                'bar' => 'bat',
            ],
        ];

        $json = json_encode($errors);
        self::assertIsString($json);
        $data = json_decode($json, true);
        self::assertSame($expect, $data);
    }

    public function testIteratorAggregate(): void
    {
        $nested = new ErrorMessages([
            'bar' => 'bat',
        ]);
        $errors = new ErrorMessages([
            'foo' => 'bar',
            'baz' => $nested,
        ]);

        $expect = [
            'foo' => 'bar',
            'baz' => $nested,
        ];

        self::assertSame($expect, iterator_to_array($errors));
    }
}
