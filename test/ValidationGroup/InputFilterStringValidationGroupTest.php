<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\ValidationGroup;

use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\StringLength;
use PHPUnit\Framework\TestCase;

final class InputFilterStringValidationGroupTest extends TestCase
{
    private InputFilter $inputFilter;

    protected function setUp(): void
    {
        parent::setUp();
        $first = new Input('first');
        $first->setRequired(true);
        $first->getValidatorChain()->attach(new StringLength(['min' => 5]));
        $second = new Input('second');
        $second->setRequired(true);
        $second->getValidatorChain()->attach(new StringLength(['min' => 5]));
        $third = new Input('third');
        $third->setRequired(true);
        $third->getValidatorChain()->attach(new StringLength(['min' => 5]));

        $this->inputFilter = new InputFilter();
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

    public function testValidationGroupWithUnknownInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"doughnuts" was not found');
        $this->inputFilter->setValidationGroup(['doughnuts']);
    }
}
