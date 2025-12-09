<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Filter\ToInt;
use Laminas\Filter\ToNull;
use Laminas\InputFilter\ArrayInput;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputInterface;
use Laminas\Translator\TranslatorInterface;
use Laminas\Validator\AbstractValidator;
use Laminas\Validator\IsArray;
use Laminas\Validator\NotEmpty as NotEmptyValidator;
use Laminas\Validator\NumberComparison;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_diff_key;
use function array_merge;
use function array_pop;
use function count;
use function iterator_to_array;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * @psalm-suppress DeprecatedMethod
 */
#[CoversClass(ArrayInput::class)]
final class ArrayInputTest extends TestCase
{
    private const EMPTY_ERROR_MESSAGE_KEY = 'isEmpty';
    private const EMPTY_ERROR_MESSAGE     = 'Value is required and can\'t be empty';

    private ArrayInput $input;

    protected function setUp(): void
    {
        $this->input = new ArrayInput(TestHelper::createFilterChain(), TestHelper::createValidatorChain(), 'foo');
    }

    protected function tearDown(): void
    {
        AbstractValidator::setDefaultTranslator();
    }

    private function createArrayInput(?string $name = null): ArrayInput
    {
        return new ArrayInput(TestHelper::createFilterChain(), TestHelper::createValidatorChain(), $name);
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
     *     1: list<string>,
     *     2: list<string>,
     *     3: bool,
     *     4: list<string>
     * }>
     */
    public static function fallbackValueVsIsValidProvider(): array
    {
        $originalValue = ['fooValue'];
        $fallbackValue = ['fooFallbackValue'];

        return [
            'Required: T, Input: Invalid. getValue: fallback'
            => [true, $fallbackValue, $originalValue, false, $fallbackValue],
            'Required: T, Input: Valid. getValue: original'
            => [true, $fallbackValue, $originalValue, true, $originalValue],
            'Required: F, Input: Invalid. getValue: fallback'
            => [false, $fallbackValue, $originalValue, false, $fallbackValue],
            'Required: F, Input: Valid. getValue: original'
            => [false, $fallbackValue, $originalValue, true, $originalValue],
        ];
    }

    /**
     * @psalm-return array<string, array{
     *     raw: list<null|string|array>,
     *     filtered: null|string|array
     * }>
     */
    public static function emptyValueProvider(): array
    {
        return [
            'null' => [
                'raw'      => [null],
                'filtered' => null,
            ],
            '""'   => [
                'raw'      => [''],
                'filtered' => '',
            ],
            /* @todo Should these cases be tested?
            '"0"' => ['0'],
            '0' => [0],
            '0.0' => [0.0],
            'false' => [false],
             */
            '[]' => [
                'raw'      => [[]],
                'filtered' => [],
            ],
        ];
    }

    /**
     * @psalm-return array<array-key, array{
     *     raw: list<bool|int|float|string|list<string>|object>,
     *     filtered:  bool|int|float|string|list<string>|object
     * }>
     */
    public static function mixedValueProvider(): array
    {
        return [
            '"0"' => [
                'raw'      => ['0'],
                'filtered' => '0',
            ],
            '0'   => [
                'raw'      => [0],
                'filtered' => 0,
            ],
            '0.0' => [
                'raw'      => [0.0],
                'filtered' => 0.0,
            ],
            /* @todo enable me
            'false' => [
            'raw' => false,
            'filtered' => false,
            ],
             */
            'php' => [
                'raw'      => ['php'],
                'filtered' => 'php',
            ],
            /* @todo enable me
            'whitespace' => [
            'raw' => ' ',
            'filtered' => ' ',
            ],
             */
            '1'       => [
                'raw'      => [1],
                'filtered' => 1,
            ],
            '1.0'     => [
                'raw'      => [1.0],
                'filtered' => 1.0,
            ],
            'true'    => [
                'raw'      => [true],
                'filtered' => true,
            ],
            '["php"]' => [
                'raw'      => [['php']],
                'filtered' => ['php'],
            ],
            'object'  => [
                'raw'      => [new stdClass()],
                'filtered' => new stdClass(),
            ],
        ];
    }

    public function testAnArrayInputViaInputFilterIsAcceptable(): void
    {
        $factory = TestHelper::createInputFilterFactory();

        $inputFilter = new InputFilter($factory);
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
        $factory = TestHelper::createInputFilterFactory();

        $inputFilter = new InputFilter($factory);
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

    public function assertRequiredValidationErrorMessage(Input $input, string $message = ''): void
    {
        $message  = $message ?: 'Expected failure message for required input';
        $message .= ';';

        $messages = $input->getMessages();

        self::assertArrayHasKey(self::EMPTY_ERROR_MESSAGE_KEY, $messages);
        self::assertEquals(
            self::EMPTY_ERROR_MESSAGE,
            $messages[self::EMPTY_ERROR_MESSAGE_KEY],
            $message . ' missing NotEmpty::IS_EMPTY key and/or contains additional messages'
        );
        self::assertCount(
            1,
            $messages,
            $message . ' missing NotEmpty::IS_EMPTY key and/or contains additional messages'
        );
    }

    public function testConstructorRequiresAName(): void
    {
        self::assertEquals('foo', $this->input->getName());
    }

    public function testInputHasEmptyFilterChainByDefault(): void
    {
        $filters = $this->input->getFilterChain();
        self::assertInstanceOf(FilterChain::class, $filters);
        self::assertCount(0, $filters);
    }

    public function testInputHasEmptyValidatorChainByDefault(): void
    {
        $validators = $this->input->getValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $validators);
        self::assertCount(0, $validators);
    }

    public function testCanInjectFilterChain(): void
    {
        $filterChain = TestHelper::createFilterChain();

        $this->input->setFilterChain($filterChain);
        self::assertSame($filterChain, $this->input->getFilterChain());
    }

    public function testCanInjectValidatorChain(): void
    {
        $validatorChain = new ValidatorChain();

        $this->input->setValidatorChain($validatorChain);
        self::assertSame($validatorChain, $this->input->getValidatorChain());
    }

    public function testInputIsMarkedAsRequiredByDefault(): void
    {
        self::assertTrue($this->input->isRequired());
    }

    public function testRequiredFlagIsMutable(): void
    {
        $this->input->setRequired(false);
        self::assertFalse($this->input->isRequired());
    }

    public function testInputDoesNotAllowEmptyValuesByDefault(): void
    {
        self::assertFalse($this->input->allowEmpty());
    }

    public function testAllowEmptyFlagIsMutable(): void
    {
        $this->input->setAllowEmpty(true);
        self::assertTrue($this->input->allowEmpty());
    }

    public function testContinueIfEmptyFlagIsFalseByDefault(): void
    {
        $input = $this->input;
        self::assertFalse($input->continueIfEmpty());
    }

    public function testContinueIfEmptyFlagIsMutable(): void
    {
        $input = $this->input;
        $input->setContinueIfEmpty(true);
        self::assertTrue($input->continueIfEmpty());
    }

    /**
     * @psalm-suppress UnusedParam Unused named parameter from data provider
     */
    #[DataProvider('setValueProvider')]
    public function testSetFallbackValue(mixed $raw, mixed $filtered): void
    {
        $input = $this->input;

        $return = $input->setFallbackValue($raw);
        self::assertSame($input, $return, 'setFallbackValue() must return it self');

        self::assertEquals($raw, $input->getFallbackValue(), 'getFallbackValue() value not match');
        self::assertTrue($input->hasFallback(), 'hasFallback() value not match');
    }

    /**
     * @psalm-suppress UnusedParam Unused named parameter for data provider
     */
    #[DataProvider('setValueProvider')]
    public function testClearFallbackValue(mixed $raw, mixed $filtered): void
    {
        $input = $this->input;
        $input->setFallbackValue($raw);
        $input->clearFallbackValue();
        self::assertNull($input->getFallbackValue(), 'getFallbackValue() value not match');
        self::assertFalse($input->hasFallback(), 'hasFallback() value not match');
    }

    /**
     * @param list<string> $fallbackValue
     * @param list<string> $originalValue
     * @param list<string> $expectedValue
     */
    #[DataProvider('fallbackValueVsIsValidProvider')]
    public function testFallbackValueVsIsValidRules(
        bool $required,
        array $fallbackValue,
        array $originalValue,
        bool $isValid,
        array $expectedValue
    ): void {
        $input = $this->input;
        $input->setContinueIfEmpty(true);

        $input->setRequired($required);
        $input->setValidatorChain(TestHelper::createValidatorChain($originalValue[0], $isValid));
        $input->setFallbackValue($fallbackValue);
        $input->setValue($originalValue);

        self::assertTrue(
            $input->isValid(),
            'isValid() should be return always true when fallback value is set. Detail: '
            . json_encode($input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals([], $input->getMessages(), 'getMessages() should be empty because the input is valid');
        self::assertSame($expectedValue, $input->getRawValue(), 'getRawValue() value not match');
        self::assertSame($expectedValue, $input->getValue(), 'getValue() value not match');
    }

    /**
     * @param list<string> $fallbackValue
     */
    #[DataProvider('fallbackValueVsIsValidProvider')]
    public function testFallbackValueVsIsValidRulesWhenValueNotSet(bool $required, array $fallbackValue): void
    {
        $expectedValue = $fallbackValue; // Should always return the fallback value

        $input = $this->input;
        $input->setContinueIfEmpty(true);

        $input->setRequired($required);
        $input->setValidatorChain(new ValidatorChain());
        $input->setFallbackValue($fallbackValue);

        self::assertTrue(
            $input->isValid(),
            'isValid() should be return always true when fallback value is set. Detail: '
            . json_encode($input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals([], $input->getMessages(), 'getMessages() should be empty because the input is valid');
        self::assertSame($expectedValue, $input->getRawValue(), 'getRawValue() value not match');
        self::assertSame($expectedValue, $input->getValue(), 'getValue() value not match');
    }

    public function testRequiredWithoutFallbackAndValueNotSetThenFail(): void
    {
        $input = $this->input;
        $input->setRequired(true);

        self::assertFalse(
            $input->isValid(),
            'isValid() should be return always false when no fallback value, is required, and not data is set.'
        );
        $this->assertRequiredValidationErrorMessage($input);
    }

    public function testRequiredWithoutFallbackAndValueNotSetThenFailReturnsCustomErrorMessageWhenSet(): void
    {
        $input = $this->input;
        $input->setRequired(true);
        $input->setErrorMessage('FAILED TO VALIDATE');

        self::assertFalse(
            $input->isValid(),
            'isValid() should be return always false when no fallback value, is required, and not data is set.'
        );
        self::assertSame(['FAILED TO VALIDATE'], $input->getMessages());
    }

    public function testRequiredWithoutFallbackAndValueNotSetProvidesNotEmptyValidatorIsEmptyErrorMessage(): void
    {
        $input = $this->input;
        $input->setRequired(true);

        self::assertFalse(
            $input->isValid(),
            'isValid() should always return false when no fallback value is present, '
            . 'the input is required, and no data is set.'
        );
        $this->assertRequiredValidationErrorMessage($input);
    }

    public function testRequiredWithoutFallbackAndValueNotSetProvidesAttachedNotEmptyValidatorIsEmptyErrorMessage(): void // phpcs:ignore
    {
        $input = $this->createArrayInput();
        $input->setRequired(true);

        $customMessage = [NotEmptyValidator::IS_EMPTY => "Custom message"];
        $notEmpty      = new NotEmptyValidator(['messages' => $customMessage]);

        $input->getValidatorChain()
            ->attach($notEmpty);

        self::assertFalse(
            $input->isValid(),
            'isValid() should always return false when no fallback value is present, '
            . 'the input is required, and no data is set.'
        );
        self::assertEquals($customMessage, $input->getMessages());
    }

    public function testRequiredWithoutFallbackAndValueNotSetProvidesCustomErrorMessageWhenSet(): void
    {
        $input = $this->input;
        $input->setRequired(true);
        $input->setErrorMessage('FAILED TO VALIDATE');

        self::assertFalse(
            $input->isValid(),
            'isValid() should always return false when no fallback value is present, '
            . 'the input is required, and no data is set.'
        );
        self::assertSame(['FAILED TO VALIDATE'], $input->getMessages());
    }

    public function testNotRequiredWithoutFallbackAndValueNotSetThenIsValid(): void
    {
        $input = $this->input;
        $input->setRequired(false);
        $input->setAllowEmpty(false);
        $input->setContinueIfEmpty(true);

        // Validator should not to be called
        $input->getValidatorChain()
            ->attach(TestHelper::createValidatorMock(null, null));
        self::assertTrue(
            $input->isValid(),
            'isValid() should be return always true when is not required, and no data is set. Detail: '
            . json_encode($input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals([], $input->getMessages(), 'getMessages() should be empty because the input is valid');
    }

    /**
     * @psalm-suppress UnusedParam Unused named parameter for data provider
     */
    #[DataProvider('emptyValueProvider')]
    public function testNotEmptyValidatorNotInjectedIfContinueIfEmptyIsTrue(mixed $raw, mixed $filtered): void
    {
        $input = $this->input;
        $input->setContinueIfEmpty(true);
        $input->setValue($raw);
        $input->isValid();
        $validators = $input->getValidatorChain()
            ->getValidators();
        self::assertEmpty($validators);
    }

    public function testValueMayBeInjected(): void
    {
        $valueRaw = 'foo';

        $this->input->setValue($valueRaw);
        self::assertEquals($valueRaw, $this->input->getValue());
    }

    public function testRetrievingValueFiltersTheValue(): void
    {
        $valueRaw      = ['foo'];
        $valueFiltered = ['filtered'];

        $filterChain = TestHelper::createFilterChainFixture($valueRaw[0], $valueFiltered[0]);

        $this->input->setFilterChain($filterChain);
        $this->input->setValue($valueRaw);

        self::assertSame($valueFiltered, $this->input->getValue());
    }

    public function testCanRetrieveRawValue(): void
    {
        $valueRaw = 'foo';

        $this->input->setFilterChain(TestHelper::createFilterChain());
        $this->input->setValue($valueRaw);

        self::assertEquals($valueRaw, $this->input->getRawValue());
    }

    public function testValidationOperatesOnFilteredValue(): void
    {
        $valueRaw      = ['foo'];
        $valueFiltered = ['filtered'];

        $filterChain = TestHelper::createFilterChainFixture($valueRaw[0], $valueFiltered[0]);

        $this->input->setAllowEmpty(true);
        $this->input->setFilterChain($filterChain);
        $this->input->setValidatorChain(TestHelper::createValidatorChain($valueFiltered[0], true));
        $this->input->setValue($valueRaw);

        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
    }

    public function testBreakOnFailureFlagIsOffByDefault(): void
    {
        self::assertFalse($this->input->breakOnFailure());
    }

    public function testBreakOnFailureFlagIsMutable(): void
    {
        $this->input->setBreakOnFailure(true);
        self::assertTrue($this->input->breakOnFailure());
    }

    /**
     * @psalm-suppress UnusedParam Unused named parameter for data provider
     */
    #[DataProvider('emptyValueProvider')]
    public function testNotEmptyValidatorAddedWhenIsValidIsCalled(mixed $raw, mixed $filtered): void
    {
        self::assertTrue($this->input->isRequired());
        $this->input->setValue($raw);
        $validatorChain = $this->input->getValidatorChain();
        self::assertEquals(0, count($validatorChain->getValidators()));

        self::assertFalse($this->input->isValid());
        $messages = $this->input->getMessages();
        self::assertArrayHasKey(self::EMPTY_ERROR_MESSAGE_KEY, $messages);
        self::assertEquals(1, count($validatorChain->getValidators()));

        // Assert that NotEmpty validator wasn't added again
        self::assertFalse($this->input->isValid());
        self::assertEquals(1, count($validatorChain->getValidators()));
    }

    /**
     * @psalm-suppress UnusedParam Unused named parameter for data provider
     */
    #[DataProvider('emptyValueProvider')]
    public function testRequiredNotEmptyValidatorNotAddedWhenOneExists(array $raw, mixed $filtered): void
    {
        $this->input->setRequired(true);
        $this->input->setValue($raw);

        $notEmpty = new NotEmptyValidator();

        $validatorChain = $this->input->getValidatorChain();
        $validatorChain->prependValidator($notEmpty);
        self::assertFalse($this->input->isValid());

        $validators = $validatorChain->getValidators();
        self::assertEquals(1, count($validators));
        self::assertEquals($notEmpty, $validators[0]['instance']);
    }

    /** @psalm-suppress UnusedParam Unused named parameter for data provider */
    #[DataProvider('emptyValueProvider')]
    public function testDoNotInjectNotEmptyValidatorIfAnywhereInChain(mixed $raw, mixed $filtered): void
    {
        $validatorChain = $this->input->getValidatorChain();

        $this->input->setRequired(true);
        $this->input->setValue($raw);

        $mockValidator = TestHelper::createValidatorMock(true);
        $validatorChain->attach($mockValidator);

        $notEmpty = new NotEmptyValidator();
        $validatorChain->attach($notEmpty);

        self::assertFalse($this->input->isValid());

        $validators = $validatorChain->getValidators();
        self::assertEquals(2, count($validators));
        self::assertEquals($mockValidator, $validators[0]['instance']);
        self::assertEquals($notEmpty, $validators[1]['instance']);
    }

    #[DataProvider('isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider')]
    #[Group('7448')]
    public function testIsRequiredVsAllowEmptyVsContinueIfEmptyVsIsValid(
        bool $required,
        bool $allowEmpty,
        bool $continueIfEmpty,
        ValidatorInterface $validator,
        mixed $value,
        bool $expectedIsValid,
        array $expectedMessages
    ): void {
        $this->input->setRequired($required);
        $this->input->setAllowEmpty($allowEmpty);
        $this->input->setContinueIfEmpty($continueIfEmpty);
        $this->input->getValidatorChain()
            ->attach($validator);
        $this->input->setValue($value);

        self::assertEquals(
            $expectedIsValid,
            $this->input->isValid(),
            'isValid() value not match. Detail: ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals($expectedMessages, $this->input->getMessages(), 'getMessages() value not match');
        self::assertEquals($value, $this->input->getRawValue(), 'getRawValue() must return the value always');
        self::assertEquals($value, $this->input->getValue(), 'getValue() must return the filtered value always');
    }

    /**
     * @psalm-suppress UnusedParam Unused named parameter for data provider
     */
    #[DataProvider('setValueProvider')]
    public function testSetValuePutInputInTheDesiredState(mixed $raw, mixed $filtered): void
    {
        $input = $this->input;
        self::assertFalse($input->hasValue(), 'Input should not have value by default');

        $input->setValue($raw);
        self::assertTrue($input->hasValue(), "hasValue() didn't return true when value was set");
    }

    /**
     * @psalm-suppress UnusedParam Unused named parameter for data provider
     */
    #[DataProvider('setValueProvider')]
    public function testResetValueReturnsInputValueToDefaultValue(mixed $raw, mixed $filtered): void
    {
        $input         = $this->input;
        $originalInput = clone $input;
        self::assertFalse($input->hasValue(), 'Input should not have value by default');

        $input->setValue($raw);
        self::assertTrue($input->hasValue(), "hasValue() didn't return true when value was set");

        $return = $input->resetValue();
        self::assertSame($input, $return, 'resetValue() must return itself');
        self::assertEquals($originalInput, $input, 'Input was not reset to the default value state');
    }

    public function testMergingTwoInputsModifiesTheName(): void
    {
        $a = $this->createArrayInput('a');
        $b = $this->createArrayInput('b');
        $a->merge($b);

        self::assertSame('b', $a->getName());
    }

    public function testMergingTwoInputsModifiesErrorMessage(): void
    {
        $a = $this->createArrayInput('a');
        $b = $this->createArrayInput('b');
        $b->setErrorMessage('Foo');
        $a->merge($b);

        self::assertSame('Foo', $a->getErrorMessage());
    }

    public function testMergingTwoInputsModifiesBreakOnFailureFlag(): void
    {
        $a = $this->createArrayInput('a');
        $a->setBreakOnFailure(false);
        $b = $this->createArrayInput('b');
        $b->setBreakOnFailure(true);
        $a->merge($b);

        self::assertTrue($a->breakOnFailure());
    }

    public function testMergingTwoInputsModifiesRequiredFlag(): void
    {
        $a = $this->createArrayInput('a');
        $a->setRequired(false);
        $b = $this->createArrayInput('b');
        $b->setRequired(true);
        $a->merge($b);

        self::assertTrue($a->isRequired());
    }

    public function testMergingTwoInputsModifiesAllowEmptyFlag(): void
    {
        $a = $this->createArrayInput('a');
        $a->setAllowEmpty(false);
        $b = $this->createArrayInput('b');
        $b->setAllowEmpty(true);
        $a->merge($b);

        self::assertTrue($a->allowEmpty());
    }

    public function testMergingTwoInputsCopiesTheValueIfSet(): void
    {
        $a = $this->createArrayInput('a');
        $a->setValue('a');
        $b = $this->createArrayInput('b');
        $b->setValue('b');
        $a->merge($b);

        self::assertSame('b', $a->getValue());
    }

    public function testThatMergingTwoInputsMergesTheFilterChain(): void
    {
        $filter1 = new ToInt();
        $filter2 = new ToNull();

        $a = $this->createArrayInput('a');
        $b = $this->createArrayInput('b');

        $a->getFilterChain()->attach($filter1);
        $b->getFilterChain()->attach($filter2);

        self::assertNotContains($filter2, $a->getFilterChain());
        self::assertCount(1, $a->getFilterChain());

        $a->merge($b);

        self::assertContains($filter2, $a->getFilterChain());
        self::assertCount(2, $a->getFilterChain());
    }

    public function testThatMergingTwoInputsMergesTheValidatorChain(): void
    {
        $validator1 = new NotEmptyValidator();
        $validator2 = new NumberComparison(['min' => 1, 'max' => 5]);

        $a = $this->createArrayInput('a');
        $b = $this->createArrayInput('b');

        $a->getValidatorChain()->attach($validator1);
        $b->getValidatorChain()->attach($validator2);

        self::assertCount(1, $a->getValidatorChain());
        self::assertValidatorChainNotContains($validator2, $a->getValidatorChain());

        $a->merge($b);

        $chain = iterator_to_array($a->getValidatorChain()->getIterator());
        self::assertCount(2, $chain);
        self::assertValidatorChainContains($validator2, $a->getValidatorChain());
    }

    private static function validatorChainContains(ValidatorInterface $validator, ValidatorChain $chain): bool
    {
        $found = false;
        foreach ($chain as $spec) {
            if ($spec['instance'] === $validator) {
                $found = true;
                break;
            }
        }

        return $found;
    }

    private static function assertValidatorChainContains(ValidatorInterface $validator, ValidatorChain $chain): void
    {
        self::assertTrue(self::validatorChainContains($validator, $chain), sprintf(
            'The validator of type "%s" was not found in the chain',
            $validator::class,
        ));
    }

    private static function assertValidatorChainNotContains(ValidatorInterface $validator, ValidatorChain $chain): void
    {
        self::assertFalse(self::validatorChainContains($validator, $chain), sprintf(
            'The validator of type "%s" was found in the chain and was not expected to be present',
            $validator::class,
        ));
    }

    public function testMerge(): void
    {
        $source = $this->createMock(InputInterface::class);
        $source->method('getName')->willReturn('bazInput');
        $source->method('getErrorMessage')->willReturn('bazErrorMessage');
        $source->method('breakOnFailure')->willReturn(true);
        $source->method('isRequired')->willReturn(true);
        $source->method('getRawValue')->willReturn('foo');
        $source->method('getFilterChain')->willReturn(TestHelper::createFilterChain());
        $source->method('getValidatorChain')->willReturn(new ValidatorChain());

        $target = $this->input;
        $target->setName('fooInput');
        $target->setErrorMessage('fooErrorMessage');
        $target->setBreakOnFailure(false);
        $target->setRequired(false);
        $target->setFilterChain(TestHelper::createFilterChain());
        $target->setValidatorChain(new ValidatorChain());

        $return = $target->merge($source);
        self::assertSame($target, $return, 'merge() must return it self');

        self::assertEquals('bazInput', $target->getName(), 'getName() value not match');
        self::assertEquals('bazErrorMessage', $target->getErrorMessage(), 'getErrorMessage() value not match');
        self::assertTrue($target->breakOnFailure(), 'breakOnFailure() value not match');
        self::assertTrue($target->isRequired(), 'isRequired() value not match');
        self::assertEquals('foo', $target->getRawValue(), 'getRawValue() value not match');
        self::assertTrue($target->hasValue(), 'hasValue() value not match');
    }

    /**
     * Specific Input::merge extras
     */
    public function testInputMergeWithoutValues(): void
    {
        $source = $this->createArrayInput();
        $source->setContinueIfEmpty(true);
        self::assertFalse($source->hasValue(), 'Source should not have a value');

        $target = $this->input;
        $target->setContinueIfEmpty(false);
        self::assertFalse($target->hasValue(), 'Target should not have a value');

        $return = $target->merge($source);
        self::assertSame($target, $return, 'merge() must return it self');

        self::assertTrue($target->continueIfEmpty(), 'continueIfEmpty() value not match');
        self::assertFalse($target->hasValue(), 'hasValue() value not match');
    }

    /**
     * Specific Input::merge extras
     */
    public function testInputMergeWithSourceValue(): void
    {
        $source = $this->createArrayInput();
        $source->setContinueIfEmpty(true);
        $source->setValue(['foo']);

        $target = $this->input;
        $target->setContinueIfEmpty(false);
        self::assertFalse($target->hasValue(), 'Target should not have a value');

        $return = $target->merge($source);
        self::assertSame($target, $return, 'merge() must return it self');

        self::assertTrue($target->continueIfEmpty(), 'continueIfEmpty() value not match');
        self::assertEquals(['foo'], $target->getRawValue(), 'getRawValue() value not match');
        self::assertTrue($target->hasValue(), 'hasValue() value not match');
    }

    /**
     * Specific Input::merge extras
     */
    public function testInputMergeWithTargetValue(): void
    {
        $source = $this->createArrayInput();
        $source->setContinueIfEmpty(true);
        self::assertFalse($source->hasValue(), 'Source should not have a value');

        $target = $this->input;
        $target->setContinueIfEmpty(false);
        $target->setValue(['foo']);

        $return = $target->merge($source);
        self::assertSame($target, $return, 'merge() must return it self');

        self::assertTrue($target->continueIfEmpty(), 'continueIfEmpty() value not match');
        self::assertEquals(['foo'], $target->getRawValue(), 'getRawValue() value not match');
        self::assertTrue($target->hasValue(), 'hasValue() value not match');
    }

    public function testNotEmptyMessageIsTranslated(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        AbstractValidator::setDefaultTranslator($translator);

        $translatedMessage = 'some translation';
        $translator->expects(self::atLeastOnce())
            ->method('translate')
            ->with(self::EMPTY_ERROR_MESSAGE, 'default')
            ->willReturn($translatedMessage);

        self::assertFalse($this->input->isValid());
        $messages = $this->input->getMessages();
        self::assertArrayHasKey(self::EMPTY_ERROR_MESSAGE_KEY, $messages);
        self::assertSame($translatedMessage, $messages[self::EMPTY_ERROR_MESSAGE_KEY]);
    }

    /**
     * @psalm-return array<array-key, array{
     *     raw: list<null|bool|int|float|string|list<string>|object>|array,
     *     filtered: null|bool|int|float|string|list<string>|object|array
     * }>
     */
    public static function setValueProvider(): array
    {
        return array_merge(static::emptyValueProvider(), static::mixedValueProvider());
    }

    /**
     * @psalm-return iterable<string, array{
     *     0: bool,
     *     1: bool,
     *     2: bool,
     *     3: ValidatorInterface,
     *     4: mixed,
     *     5: bool,
     *     6: string[]
     * }>
     */
    public static function isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider(): iterable
    {
        $allValues   = static::setValueProvider();
        $emptyValues = static::emptyValueProvider();

        $nonEmptyValues = array_diff_key($allValues, $emptyValues);

        $validatorMsg = ['FooValidator' => 'Invalid Value'];
        $notEmptyMsg  = [self::EMPTY_ERROR_MESSAGE_KEY => "Value is required and can't be empty"];

        $validatorNotCall = fn(mixed $value, array|null $context = null): ValidatorInterface =>
        TestHelper::createValidatorMock(null, $value, $context);
        $validatorInvalid = fn(mixed $value, array|null $context = null): ValidatorInterface =>
        TestHelper::createValidatorMock(false, $value, $context, $validatorMsg);
        $validatorValid   = fn(mixed $value, array|null $context = null): ValidatorInterface =>
        TestHelper::createValidatorMock(true, $value, $context);

        $dataTemplates = [
            'Required: T; AEmpty: T; CIEmpty: T; Validator: T'
            => [true, true, true, $validatorValid, $allValues, true, []],
            'Required: T; AEmpty: T; CIEmpty: T; Validator: F'
            => [true, true, true, $validatorInvalid, $allValues, false, $validatorMsg],
            'Required: T; AEmpty: T; CIEmpty: F; Validator: X, Value: Empty'
            => [true, true, false, $validatorNotCall, $emptyValues, true, []],
            'Required: T; AEmpty: T; CIEmpty: F; Validator: T, Value: Not Empty'
            => [true, true, false, $validatorValid, $nonEmptyValues, true, []],
            'Required: T; AEmpty: T; CIEmpty: F; Validator: F, Value: Not Empty'
            => [true, true, false, $validatorInvalid, $nonEmptyValues, false, $validatorMsg],
            'Required: T; AEmpty: F; CIEmpty: T; Validator: T'
            => [true, false, true, $validatorValid, $allValues, true, []],
            'Required: T; AEmpty: F; CIEmpty: T; Validator: F'
            => [true, false, true, $validatorInvalid, $allValues, false, $validatorMsg],
            'Required: T; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty'
            => [true, false, false, $validatorNotCall, $emptyValues, false, $notEmptyMsg],
            'Required: T; AEmpty: F; CIEmpty: F; Validator: T, Value: Not Empty'
            => [true, false, false, $validatorValid, $nonEmptyValues, true, []],
            'Required: T; AEmpty: F; CIEmpty: F; Validator: F, Value: Not Empty'
            => [true, false, false, $validatorInvalid, $nonEmptyValues, false, $validatorMsg],
            'Required: F; AEmpty: T; CIEmpty: T; Validator: T'
            => [false, true, true, $validatorValid, $allValues, true, []],
            'Required: F; AEmpty: T; CIEmpty: T; Validator: F'
            => [false, true, true, $validatorInvalid, $allValues, false, $validatorMsg],
            'Required: F; AEmpty: T; CIEmpty: F; Validator: X, Value: Empty'
            => [false, true, false, $validatorNotCall, $emptyValues, true, []],
            'Required: F; AEmpty: T; CIEmpty: F; Validator: T, Value: Not Empty'
            => [false, true, false, $validatorValid, $nonEmptyValues, true, []],
            'Required: F; AEmpty: T; CIEmpty: F; Validator: F, Value: Not Empty'
            => [false, true, false, $validatorInvalid, $nonEmptyValues, false, $validatorMsg],
            'Required: F; AEmpty: F; CIEmpty: T; Validator: T'
            => [false, false, true, $validatorValid, $allValues, true, []],
            'Required: F; AEmpty: F; CIEmpty: T; Validator: F'
            => [false, false, true, $validatorInvalid, $allValues, false, $validatorMsg],
            'Required: F; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty'
            => [false, false, false, $validatorNotCall, $emptyValues, true, []],
            'Required: F; AEmpty: F; CIEmpty: F; Validator: T, Value: Not Empty'
            => [false, false, false, $validatorValid, $nonEmptyValues, true, []],
            'Required: F; AEmpty: F; CIEmpty: F; Validator: F, Value: Not Empty'
            => [false, false, false, $validatorInvalid, $nonEmptyValues, false, $validatorMsg],
        ];

        // Expand data template matrix for each possible input value.
        // Description => [$isRequired, $allowEmpty, $continueIfEmpty, $validator, $value, $expectedIsValid]
        $dataSets = [];
        foreach ($dataTemplates as $dataTemplateDescription => $dataTemplate) {
            foreach ($dataTemplate[4] as $valueDescription => $value) {
                $tmpTemplate    = $dataTemplate;
                $tmpTemplate[3] = $dataTemplate[3]($value['filtered']); // Get validator mock for each data set
                $tmpTemplate[4] = $value['raw']; // expand value

                $dataSets[$dataTemplateDescription . ' / ' . $valueDescription] = $tmpTemplate;
            }
        }

        return $dataSets;
    }
}
