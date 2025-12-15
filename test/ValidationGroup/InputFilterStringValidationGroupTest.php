<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\ValidationGroup;

use Laminas\InputFilter\Exception\InputNotFoundException;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\StringLength;
use LaminasTest\InputFilter\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * Test case for basic behaviour of validation groups
 */
final class InputFilterStringValidationGroupTest extends TestCase
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

        $this->inputFilter = new InputFilter($factory);
        $this->inputFilter->add($first);
        $this->inputFilter->add($second);
        $this->inputFilter->add($third);
    }

    public function testValidationFailsForIncompleteInput(): void
    {
        $this->inputFilter->setData(['first' => 'Freddy']);
        self::assertFalse($this->inputFilter->isValid());
    }

    public function testValidationSucceedsForCompleteInput(): void
    {
        $this->inputFilter->setData(['first' => 'Freddy', 'second' => 'Fruit Bat', 'third' => 'Muppet']);
        self::assertTrue($this->inputFilter->isValid());
    }

    public function testValidationSucceedsForIncompleteInputWhenValidationGroupIsProvided(): void
    {
        $this->inputFilter->setValidationGroup('first');
        $this->inputFilter->setData(['first' => 'Freddy']);

        self::assertTrue($this->inputFilter->isValid());
    }

    public function testThatValidationGroupIsVariadic(): void
    {
        $this->inputFilter->setValidationGroup('first', 'second');
        $this->inputFilter->setData(['first' => 'Freddy', 'second' => 'Fruit Bat']);

        self::assertTrue($this->inputFilter->isValid());
    }

    public function testThatValidationGroupAcceptsListOfInputNames(): void
    {
        $this->inputFilter->setValidationGroup(['first', 'second']);
        $this->inputFilter->setData(['first' => 'Freddy', 'second' => 'Fruit Bat']);

        self::assertTrue($this->inputFilter->isValid());
    }

    public function testValidationGroupArrayWithUnknownInputIsExceptional(): void
    {
        $this->expectException(InputNotFoundException::class);
        $this->expectExceptionMessage('The input or input filter named "doughnuts" cannot be found');
        $this->inputFilter->setValidationGroup(['doughnuts']);
    }

    public function testValidationGroupStringWithUnknownInputIsExceptional(): void
    {
        $this->expectException(InputNotFoundException::class);
        $this->expectExceptionMessage('The input or input filter named "not-there" cannot be found');
        $this->inputFilter->setValidationGroup('not-there');
    }

    public function testArrayArgumentsForAnInputInAValidationGroupIsIgnored(): void
    {
        $this->inputFilter->setValidationGroup(['first' => ['not-there']]);

        $this->inputFilter->setData(['first' => 'Freddy']);
        self::assertTrue($this->inputFilter->isValid());

        $this->inputFilter->setData(['second' => 'Freddy']);
        self::assertFalse($this->inputFilter->isValid());
    }

    public function testSetValidationGroupHasFluentInterface(): void
    {
        self::assertSame(
            $this->inputFilter,
            $this->inputFilter->setValidationGroup('first'),
        );
    }
}
