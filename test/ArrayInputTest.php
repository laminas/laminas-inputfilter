<?php

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\ArrayInput;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Webmozart\Assert\Assert;

use function array_map;
use function array_pop;
use function array_walk;
use function current;
use function is_array;

/**
 * @covers \Laminas\InputFilter\ArrayInput
 */
class ArrayInputTest extends InputTest
{
    protected function setUp(): void
    {
        $this->input = new ArrayInput('foo');
    }

    public function testDefaultGetValue(): void
    {
        $this->assertCount(0, $this->input->getValue());
    }

    public function testArrayInputMarkedRequiredWithoutAFallbackFailsValidationForEmptyArrays(): void
    {
        $input = $this->input;
        $input->setRequired(true);
        $input->setValue([]);

        $this->assertFalse($input->isValid());
        $this->assertRequiredValidationErrorMessage($input);
    }

    public function testArrayInputMarkedRequiredWithoutAFallbackUsesProvidedErrorMessageOnFailureDueToEmptyArray(): void
    {
        $expected = 'error message';

        $input = $this->input;
        $input->setRequired(true);
        $input->setErrorMessage($expected);
        $input->setValue([]);

        $this->assertFalse($input->isValid());

        $messages = $input->getMessages();
        $this->assertCount(1, $messages);
        $message = array_pop($messages);
        $this->assertEquals($expected, $message);
    }

    public function testSetValueWithInvalidInputTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be an array, string given');
        $this->input->setValue('bar');
    }

    /**
     * @psalm-return array<string, array{
     *     0: bool,
     *     1: string[],
     *     2: string[],
     *     3: bool,
     *     4: string[]
     * }>
     */
    public function fallbackValueVsIsValidProvider(): array
    {
        $dataSets = parent::fallbackValueVsIsValidProvider();
        Assert::isArray($dataSets);
        array_walk($dataSets, function (&$set) {
            $set[1] = [$set[1]]; // Wrap fallback value into an array.
            $set[2] = [$set[2]]; // Wrap value into an array.
            $set[4] = [$set[4]]; // Wrap expected value into an array.
        });

        return $dataSets;
    }

    /**
     * @psalm-return iterable<string, array{
     *     raw: list<null|string|array>,
     *     filtered: null|string|array
     * }>
     */
    public function emptyValueProvider(): iterable
    {
        $dataSets = parent::emptyValueProvider();
        Assert::isArray($dataSets);
        array_walk($dataSets, function (&$set) {
            $set['raw'] = [$set['raw']]; // Wrap value into an array.
        });

        return $dataSets;
    }

    /**
     * @psalm-return array<string, array{
     *     raw: list<bool|int|float|string|list<string>|object>,
     *     filtered:  bool|int|float|string|list<string>|object
     * }>
     */
    public function mixedValueProvider(): array
    {
        $dataSets = parent::mixedValueProvider();
        Assert::isArray($dataSets);
        array_walk($dataSets, function (&$set) {
            $set['raw'] = [$set['raw']]; // Wrap value into an array.
        });

        return $dataSets;
    }

    /**
     * @param array $valueMap
     * @return FilterChain&MockObject
     */
    protected function createFilterChainMock(array $valueMap = [])
    {
        // ArrayInput filters per each array value
        $valueMap = array_map(
            function ($values) {
                if (is_array($values[0])) {
                    $values[0] = current($values[0]);
                }
                if (is_array($values[1])) {
                    $values[1] = current($values[1]);
                }
                return $values;
            },
            $valueMap
        );

        return parent::createFilterChainMock($valueMap);
    }

    /**
     * @param array $valueMap
     * @param string[] $messages
     * @return ValidatorChain&MockObject
     */
    protected function createValidatorChainMock(array $valueMap = [], $messages = [])
    {
        // ArrayInput validates per each array value
        $valueMap = array_map(
            function ($values) {
                if (is_array($values[0])) {
                    $values[0] = current($values[0]);
                }
                return $values;
            },
            $valueMap
        );

        return parent::createValidatorChainMock($valueMap, $messages);
    }

    /**
     * @param bool $isValid
     * @param mixed $value
     * @param mixed $context
     * @return NotEmptyValidator&MockObject
     */
    protected function createNonEmptyValidatorMock($isValid, $value, $context = null)
    {
        // ArrayInput validates per each array value
        if (is_array($value)) {
            $value = current($value);
        }

        return parent::createNonEmptyValidatorMock($isValid, $value, $context);
    }

    /** @return string[] */
    protected function getDummyValue(bool $raw = true)
    {
        return [parent::getDummyValue($raw)];
    }
}
