<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use ArrayIterator;
use Exception;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\OptionalInputFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptionalInputFilter::class)]
class OptionalInputFilterTest extends TestCase
{
    public function testValidatesSuccessfullyWhenSetDataIsNeverCalled(): void
    {
        self::assertTrue($this->getNestedCarInputFilter()->get('car')->isValid());
    }

    public function testValidatesSuccessfullyWhenValidNonEmptyDataSetProvided(): void
    {
        $data = [
            'car' => [
                'brand' => 'Volkswagen',
                'model' => 'Golf',
            ],
        ];

        $inputFilter = $this->getNestedCarInputFilter();
        $inputFilter->setData($data);

        self::assertTrue($inputFilter->isValid());
        self::assertEquals($data, $inputFilter->getValues());
    }

    public function testValidatesSuccessfullyWhenEmptyDataSetProvided(): void
    {
        $data = [
            'car' => null,
        ];

        $inputFilter = $this->getNestedCarInputFilter();
        $inputFilter->setData($data);

        self::assertTrue($inputFilter->isValid());
        self::assertEquals($data, $inputFilter->getValues());
    }

    public function testValidatesSuccessfullyWhenNoDataProvided(): void
    {
        $data = [];

        $inputFilter = $this->getNestedCarInputFilter();
        $inputFilter->setData($data);

        self::assertTrue($inputFilter->isValid());
        self::assertEquals(['car' => null], $inputFilter->getValues());
    }

    public function testValidationFailureWhenInvalidDataSetIsProvided(): void
    {
        $inputFilter = $this->getNestedCarInputFilter();
        $inputFilter->setData([
            'car' => [
                'brand' => 'Volkswagen',
            ],
        ]);

        self::assertFalse($inputFilter->isValid());
        $this->assertGetValuesThrows($inputFilter);
    }

    public function testStateIsClearedBetweenValidationAttempts(): void
    {
        $data = [
            'car' => null,
        ];

        $inputFilter = $this->getNestedCarInputFilter();
        $inputFilter->setData($data);

        self::assertTrue($inputFilter->isValid());
        self::assertEquals($data, $inputFilter->getValues());
    }

    /**
     * We are doing some boolean shenanigans in the implementation
     * we want to check that Iterator objects work the same as arrays
     */
    public function testIteratorBehavesTheSameAsArray(): void
    {
        $optionalInputFilter = new OptionalInputFilter();
        $optionalInputFilter->add(new Input('brand'));

        $optionalInputFilter->setData(['model' => 'Golf']);
        self::assertFalse($optionalInputFilter->isValid());

        $optionalInputFilter->setData(new ArrayIterator([]));
        self::assertTrue($optionalInputFilter->isValid());

        $optionalInputFilter->setData([]);
        self::assertTrue($optionalInputFilter->isValid());
    }

    protected function assertGetValuesThrows(InputFilterInterface $inputFilter): void
    {
        try {
            $inputFilter->getValues();
            self::fail('No exception was thrown');
        // TODO: issue #143 narrow which exception should be thrown
        } catch (Exception $exception) {
            self::assertTrue(true);
        }
    }

    private ?InputFilter $nestedCarInputFilter = null;

    protected function getNestedCarInputFilter(): InputFilter
    {
        if (! $this->nestedCarInputFilter) {
            $optionalInputFilter = new OptionalInputFilter();
            $optionalInputFilter->add(new Input('brand'));
            $optionalInputFilter->add(new Input('model'));

            $this->nestedCarInputFilter = new InputFilter();
            $this->nestedCarInputFilter->add($optionalInputFilter, 'car');
        }

        return $this->nestedCarInputFilter;
    }
}
