<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\ArrayInput;
use Laminas\InputFilter\Exception\InvalidArgumentException;

/**
 * @covers Laminas\InputFilter\ArrayInput
 */
class ArrayInputTest extends InputTest
{
    public function setUp()
    {
        $this->input = new ArrayInput('foo');
    }

    public function testDefaultGetValue()
    {
        $this->assertCount(0, $this->input->getValue());
    }

    public function testSetValueWithInvalidInputTypeThrowsInvalidArgumentException()
    {
        $this->setExpectedException(
            InvalidArgumentException::class,
            'Value must be an array, string given'
        );
        $this->input->setValue('bar');
    }

    public function fallbackValueVsIsValidProvider()
    {
        $dataSets = parent::fallbackValueVsIsValidProvider();
        array_walk($dataSets, function (&$set) {
            $set[1] = [$set[1]]; // Wrap fallback value into an array.
            $set[2] = [$set[2]]; // Wrap value into an array.
            $set[4] = [$set[4]]; // Wrap expected value into an array.
        });

        return $dataSets;
    }

    public function emptyValueProvider()
    {
        $dataSets = parent::emptyValueProvider();
        array_walk($dataSets, function (&$set) {
            $set['raw'] = [$set['raw']]; // Wrap value into an array.
        });

        return $dataSets;
    }

    public function mixedValueProvider()
    {
        $dataSets = parent::mixedValueProvider();
        array_walk($dataSets, function (&$set) {
            $set['raw'] = [$set['raw']]; // Wrap value into an array.
        });

        return $dataSets;
    }

    protected function createFilterChainMock($valueRaw = null, $valueFiltered = null)
    {
        // ArrayInput filters per each array value
        if (is_array($valueRaw)) {
            $valueRaw = current($valueRaw);
        }

        if (is_array($valueFiltered)) {
            $valueFiltered = current($valueFiltered);
        }

        return parent::createFilterChainMock($valueRaw, $valueFiltered);
    }

    protected function createValidatorChainMock($isValid = null, $value = null, $context = null, $messages = [])
    {
        // ArrayInput validates per each array value
        if (is_array($value)) {
            $value = current($value);
        }

        return parent::createValidatorChainMock($isValid, $value, $context, $messages);
    }

    protected function createNonEmptyValidatorMock($isValid, $value, $context =  null)
    {
        // ArrayInput validates per each array value
        if (is_array($value)) {
            $value = current($value);
        }

        return parent::createNonEmptyValidatorMock($isValid, $value, $context);
    }

    protected function getDummyValue($raw = true)
    {
        return [parent::getDummyValue($raw)];
    }
}
