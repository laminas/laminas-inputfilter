<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\ValidationGroup;

use Laminas\InputFilter\Exception\InputNotFoundException;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Validator\StringLength;
use LaminasTest\InputFilter\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * Test case to cover validationGroups with nested input filters
 */
final class InputFilterNestedValidationGroupTest extends TestCase
{
    private InputFilter $inputFilter;

    protected function setUp(): void
    {
        $factory = TestHelper::createInputFilterFactory();

        $first = $factory->createInput([
            'name'       => 'first',
            'required'   => true,
            'validators' => [
                [
                    'name'    => StringLength::class,
                    'options' => ['min' => 5],
                ],
            ],
        ]);

        $second = $factory->createInput([
            'name'       => 'second',
            'required'   => true,
            'validators' => [
                [
                    'name'    => StringLength::class,
                    'options' => ['min' => 5],
                ],
            ],
        ]);

        $third = $factory->createInput([
            'name'       => 'third',
            'required'   => true,
            'validators' => [
                [
                    'name'    => StringLength::class,
                    'options' => ['min' => 5],
                ],
            ],
        ]);

        $fourth = $factory->createInput([
            'name'       => 'fourth',
            'required'   => true,
            'validators' => [
                [
                    'name'    => StringLength::class,
                    'options' => ['min' => 5],
                ],
            ],
        ]);

        $this->inputFilter = new InputFilter($factory);
        $this->inputFilter->add($first);
        $this->inputFilter->add($second);
        $nested = new InputFilter($factory);
        $nested->add($third);
        $nested->add($fourth);

        $this->inputFilter->add($nested, 'nested');
    }

    public function testValidationFailsForMissingInput(): void
    {
        $this->inputFilter->setData([]);
        self::assertFalse($this->inputFilter->isValid());
    }

    public function testValidationSucceedsForValidInput(): void
    {
        $this->inputFilter->setData([
            'first'  => 'Muppet',
            'second' => 'Muppet',
            'nested' => [
                'third'  => 'Muppet',
                'fourth' => 'Muppet',
            ],
        ]);

        self::assertTrue($this->inputFilter->isValid());
    }

    public function testValidateSingleInputByName(): void
    {
        $this->inputFilter->setValidationGroup('first');
        $this->inputFilter->setData([
            'first' => 'Muppet',
        ]);

        self::assertTrue($this->inputFilter->isValid());
    }

    public function testNestedValidationGroup(): void
    {
        $this->inputFilter->setValidationGroup([
            'first',
            'nested' => ['third'],
        ]);

        $this->inputFilter->setData([
            'first'  => 'Muppet',
            'nested' => [
                'third' => 'Muppet',
            ],
        ]);

        self::assertTrue($this->inputFilter->isValid());

        $this->inputFilter->setData([
            'first' => 'Muppet',
        ]);

        self::assertFalse($this->inputFilter->isValid());
    }

    public function testValidationGroupIsResetRecursivelyWithValidateAllArgument(): void
    {
        $this->inputFilter->setValidationGroup([
            'first',
            'nested' => ['third'],
        ]);

        $this->inputFilter->setValidationGroup(InputFilterInterface::VALIDATE_ALL);

        $this->inputFilter->setData([
            'first'  => 'Muppet',
            'nested' => [
                'third' => 'Muppet',
            ],
        ]);

        self::assertFalse($this->inputFilter->isValid());

        $this->inputFilter->setData([
            'first'  => 'Muppet',
            'second' => 'Muppet',
            'nested' => [
                'third'  => 'Muppet',
                'fourth' => 'Muppet',
            ],
        ]);

        self::assertTrue($this->inputFilter->isValid());
    }

    public function testValidationGroupWithUnknownInputInNestedFilterIsExceptional(): void
    {
        $this->expectException(InputNotFoundException::class);
        $this->expectExceptionMessage('The input or input filter named "not-there" cannot be found');
        $this->inputFilter->setValidationGroup(['nested' => ['not-there']]);
    }

    public function testValuesRetrievalIsInfluencedByValidationGroup(): void
    {
        $this->inputFilter->setValidationGroup([
            'first',
            'nested' => ['third'],
        ]);

        $completeData = [
            'first'  => 'Muppet',
            'second' => 'Muppet',
            'nested' => [
                'third'  => 'Muppet',
                'fourth' => 'Muppet',
            ],
        ];

        $this->inputFilter->setData($completeData);

        $expect = [
            'first'  => 'Muppet',
            'nested' => [
                'third' => 'Muppet',
            ],
        ];

        self::assertSame($expect, $this->inputFilter->getValues());

        $this->inputFilter->setValidationGroup(InputFilterInterface::VALIDATE_ALL);

        self::assertSame($completeData, $this->inputFilter->getValues());
    }
}
