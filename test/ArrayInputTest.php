<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\InputFilter\ArrayInput;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\IsArray;
use Laminas\Validator\NotEmpty as NotEmptyValidator;
use Laminas\Validator\ValidatorChain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Webmozart\Assert\Assert;

use function array_map;
use function array_pop;
use function array_walk;
use function current;
use function is_array;

#[CoversClass(ArrayInput::class)]
class ArrayInputTest extends InputTest
{
    protected function setUp(): void
    {
        $this->input = new ArrayInput('foo');
    }

    /**
     * @deprecated Since 2.30.1 The default value should be null in the next major,
     *             therefore this test can be dropped in favour of parent::testDefaultGetValue()
     */
    public function testDefaultGetValue(): void
    {
        self::assertSame([], $this->input->getValue());
    }

    public function testArrayInputMarkedRequiredWithoutAFallbackFailsValidationForEmptyArrays(): void
    {
        $input = $this->input;
        $input->setRequired(true);
        $input->setValue([]);

        self::assertFalse($input->isValid());
        $this->assertRequiredValidationErrorMessage($input);
    }

    public function testArrayInputMarkedRequiredWithoutAFallbackUsesProvidedErrorMessageOnFailureDueToEmptyArray(): void
    {
        $expected = 'error message';

        $input = $this->input;
        $input->setRequired(true);
        $input->setErrorMessage($expected);
        $input->setValue([]);

        self::assertFalse($input->isValid());

        $messages = $input->getMessages();
        self::assertCount(1, $messages);
        $message = array_pop($messages);
        self::assertEquals($expected, $message);
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
    public static function fallbackValueVsIsValidProvider(): array
    {
        $dataSets = parent::fallbackValueVsIsValidProvider();
        Assert::isArray($dataSets);
        array_walk($dataSets, static function (&$set) {
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
    public static function emptyValueProvider(): iterable
    {
        $dataSets = parent::emptyValueProvider();
        Assert::isArray($dataSets);
        array_walk($dataSets, static function (&$set) {
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
    public static function mixedValueProvider(): array
    {
        $dataSets = parent::mixedValueProvider();
        Assert::isArray($dataSets);
        array_walk($dataSets, static function (&$set) {
            $set['raw'] = [$set['raw']]; // Wrap value into an array.
        });

        return $dataSets;
    }

    /**
     * @param list<list<mixed>> $valueMap
     * @return FilterChain&MockObject
     */
    protected function createFilterChainMock(array $valueMap = [])
    {
        // ArrayInput filters per each array value
        $valueMap = array_map(
            static function ($values) {
                if (is_array($values[0])) {
                    $values[0] = current($values[0]);
                }
                if (is_array($values[1])) {
                    $values[1] = current($values[1]);
                }
                return $values;
            },
            $valueMap,
        );

        return parent::createFilterChainMock($valueMap);
    }

    /**
     * @param list<list<mixed>> $valueMap
     * @param string[] $messages
     * @return ValidatorChain&MockObject
     */
    protected function createValidatorChainMock(array $valueMap = [], $messages = [])
    {
        // ArrayInput validates per each array value
        $valueMap = array_map(
            static function ($values) {
                if (is_array($values[0])) {
                    $values[0] = current($values[0]);
                }
                return $values;
            },
            $valueMap,
        );

        return parent::createValidatorChainMock($valueMap, $messages);
    }

    protected function createNonEmptyValidatorMock(
        bool $isValid,
        mixed $value,
        mixed $context = null,
    ): NotEmptyValidator&MockObject {
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

    public function testAnArrayInputViaInputFilterIsAcceptable(): void
    {
        $inputFilter = new InputFilter();
        $inputFilter->add([
            'type'       => ArrayInput::class,
            'validators' => [
                ['name' => NotEmptyValidator::class],
            ],
        ], 'myInput');

        $inputFilter->setData(['myInput' => ['foo', 'bar']]);
        self::assertTrue($inputFilter->isValid());
    }

    /** @return array<string, array{0: mixed}> */
    public static function nonArrayInput(): array
    {
        return [
            'String'       => ['foo'],
            'Empty String' => [''],
            'Null'         => [null],
            'Object'       => [(object) ['foo' => 'bar']],
            'Float'        => [1.23],
            'Integer'      => [123],
            'Boolean'      => [true],
        ];
    }

    #[DataProvider('nonArrayInput')]
    public function testNonArrayValueIsValidationFailure(mixed $value): void
    {
        $this->input->setValue($value);
        self::assertFalse($this->input->isValid());
    }

    #[DataProvider('nonArrayInput')]
    public function testNonArrayInputViaInputFilterIsUnacceptable(mixed $value): void
    {
        $inputFilter = new InputFilter();
        $inputFilter->add([
            'type'       => ArrayInput::class,
            'validators' => [
                ['name' => NotEmptyValidator::class],
            ],
        ], 'myInput');

        $inputFilter->setData(['myInput' => $value]);
        self::assertFalse($inputFilter->isValid());
        $messages = $inputFilter->getMessages()['myInput'] ?? null;
        self::assertIsArray($messages);
        self::assertArrayHasKey(IsArray::NOT_ARRAY, $messages);
        self::assertIsString($messages[IsArray::NOT_ARRAY]);
        self::assertStringStartsWith(
            'Expected an array value but ',
            $messages[IsArray::NOT_ARRAY],
        );
    }
}
