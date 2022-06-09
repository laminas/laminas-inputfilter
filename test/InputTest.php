<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps

namespace LaminasTest\InputFilter;

use Iterator;
use Laminas\Filter\FilterChain;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputInterface;
use Laminas\Validator\AbstractValidator;
use Laminas\Validator\NotEmpty as NotEmptyValidator;
use Laminas\Validator\Translator\TranslatorInterface;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use stdClass;
use Webmozart\Assert\Assert;

use function array_diff_key;
use function array_merge;
use function count;
use function iterator_to_array;
use function json_encode;

/**
 * @psalm-suppress DeprecatedMethod
 */
class InputTest extends TestCase
{
    use ProphecyTrait;

    /** @var Input */
    protected $input;

    protected function setUp(): void
    {
        $this->input = new Input('foo');
    }

    protected function tearDown(): void
    {
        AbstractValidator::setDefaultTranslator(null);
    }

    public function assertRequiredValidationErrorMessage(Input $input, string $message = ''): void
    {
        $message  = $message ?: 'Expected failure message for required input';
        $message .= ';';

        $expectedKey = NotEmptyValidator::IS_EMPTY;
        $messages    = $input->getMessages();
        self::assertArrayHasKey($expectedKey, $messages);

        $notEmpty         = new NotEmptyValidator();
        $messageTemplates = $notEmpty->getOption('messageTemplates');
        self::assertIsArray($messageTemplates);
        self::assertArrayHasKey($expectedKey, $messageTemplates);
        self::assertEquals(
            $messageTemplates[$expectedKey],
            $messages[$expectedKey],
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
        $this->assertEquals('foo', $this->input->getName());
    }

    public function testInputHasEmptyFilterChainByDefault(): void
    {
        $filters = $this->input->getFilterChain();
        $this->assertInstanceOf(FilterChain::class, $filters);
        $this->assertEquals(0, count($filters));
    }

    public function testInputHasEmptyValidatorChainByDefault(): void
    {
        $validators = $this->input->getValidatorChain();
        $this->assertInstanceOf(ValidatorChain::class, $validators);
        $this->assertEquals(0, count($validators));
    }

    public function testCanInjectFilterChain(): void
    {
        $chain = $this->createFilterChainMock();
        $this->input->setFilterChain($chain);
        $this->assertSame($chain, $this->input->getFilterChain());
    }

    public function testCanInjectValidatorChain(): void
    {
        $chain = $this->createValidatorChainMock();
        $this->input->setValidatorChain($chain);
        $this->assertSame($chain, $this->input->getValidatorChain());
    }

    public function testInputIsMarkedAsRequiredByDefault(): void
    {
        $this->assertTrue($this->input->isRequired());
    }

    public function testRequiredFlagIsMutable(): void
    {
        $this->input->setRequired(false);
        $this->assertFalse($this->input->isRequired());
    }

    public function testInputDoesNotAllowEmptyValuesByDefault(): void
    {
        $this->assertFalse($this->input->allowEmpty());
    }

    public function testAllowEmptyFlagIsMutable(): void
    {
        $this->input->setAllowEmpty(true);
        $this->assertTrue($this->input->allowEmpty());
    }

    public function testContinueIfEmptyFlagIsFalseByDefault(): void
    {
        $input = $this->input;
        $this->assertFalse($input->continueIfEmpty());
    }

    public function testContinueIfEmptyFlagIsMutable(): void
    {
        $input = $this->input;
        $input->setContinueIfEmpty(true);
        $this->assertTrue($input->continueIfEmpty());
    }

    /**
     * @dataProvider setValueProvider
     * @param mixed $fallbackValue
     */
    public function testSetFallbackValue($fallbackValue): void
    {
        $input = $this->input;

        $return = $input->setFallbackValue($fallbackValue);
        $this->assertSame($input, $return, 'setFallbackValue() must return it self');

        $this->assertEquals($fallbackValue, $input->getFallbackValue(), 'getFallbackValue() value not match');
        $this->assertTrue($input->hasFallback(), 'hasFallback() value not match');
    }

    /**
     * @dataProvider setValueProvider
     * @param mixed $fallbackValue
     */
    public function testClearFallbackValue($fallbackValue): void
    {
        $input = $this->input;
        $input->setFallbackValue($fallbackValue);
        $input->clearFallbackValue();
        $this->assertNull($input->getFallbackValue(), 'getFallbackValue() value not match');
        $this->assertFalse($input->hasFallback(), 'hasFallback() value not match');
    }

    /**
     * @dataProvider fallbackValueVsIsValidProvider
     * @param string|string[] $fallbackValue
     * @param string|string[] $originalValue
     * @param string|string[] $expectedValue
     */
    public function testFallbackValueVsIsValidRules(
        bool $required,
        $fallbackValue,
        $originalValue,
        bool $isValid,
        $expectedValue
    ): void {
        $input = $this->input;
        $input->setContinueIfEmpty(true);

        $input->setRequired($required);
        $input->setValidatorChain($this->createValidatorChainMock([[$originalValue, null, $isValid]]));
        $input->setFallbackValue($fallbackValue);
        $input->setValue($originalValue);

        $this->assertTrue(
            $input->isValid(),
            'isValid() should be return always true when fallback value is set. Detail: '
            . json_encode($input->getMessages())
        );
        $this->assertEquals([], $input->getMessages(), 'getMessages() should be empty because the input is valid');
        $this->assertSame($expectedValue, $input->getRawValue(), 'getRawValue() value not match');
        $this->assertSame($expectedValue, $input->getValue(), 'getValue() value not match');
    }

    /**
     * @dataProvider fallbackValueVsIsValidProvider
     * @param string|string[] $fallbackValue
     */
    public function testFallbackValueVsIsValidRulesWhenValueNotSet(bool $required, $fallbackValue): void
    {
        $expectedValue = $fallbackValue; // Should always return the fallback value

        $input = $this->input;
        $input->setContinueIfEmpty(true);

        $input->setRequired($required);
        $input->setValidatorChain($this->createValidatorChainMock());
        $input->setFallbackValue($fallbackValue);

        $this->assertTrue(
            $input->isValid(),
            'isValid() should be return always true when fallback value is set. Detail: '
            . json_encode($input->getMessages())
        );
        $this->assertEquals([], $input->getMessages(), 'getMessages() should be empty because the input is valid');
        $this->assertSame($expectedValue, $input->getRawValue(), 'getRawValue() value not match');
        $this->assertSame($expectedValue, $input->getValue(), 'getValue() value not match');
    }

    public function testRequiredWithoutFallbackAndValueNotSetThenFail(): void
    {
        $input = $this->input;
        $input->setRequired(true);

        $this->assertFalse(
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

        $this->assertFalse(
            $input->isValid(),
            'isValid() should be return always false when no fallback value, is required, and not data is set.'
        );
        $this->assertSame(['FAILED TO VALIDATE'], $input->getMessages());
    }

    public function testRequiredWithoutFallbackAndValueNotSetProvidesNotEmptyValidatorIsEmptyErrorMessage(): void
    {
        $input = $this->input;
        $input->setRequired(true);

        $this->assertFalse(
            $input->isValid(),
            'isValid() should always return false when no fallback value is present, '
            . 'the input is required, and no data is set.'
        );
        $this->assertRequiredValidationErrorMessage($input);
    }

    public function testRequiredWithoutFallbackAndValueNotSetProvidesAttachedNotEmptyValidatorIsEmptyErrorMessage(): void // phpcs:ignore
    {
        $input = new Input();
        $input->setRequired(true);

        $customMessage = [
            NotEmptyValidator::IS_EMPTY => "Custom message",
        ];

        $notEmpty = $this->getMockBuilder(NotEmptyValidator::class)
            ->setMethods(['getOption'])
            ->getMock();

        $notEmpty->expects($this->once())
            ->method('getOption')
            ->with('messageTemplates')
            ->willReturn($customMessage);

        $input->getValidatorChain()
            ->attach($notEmpty);

        $this->assertFalse(
            $input->isValid(),
            'isValid() should always return false when no fallback value is present, '
            . 'the input is required, and no data is set.'
        );
        $this->assertEquals($customMessage, $input->getMessages());
    }

    public function testRequiredWithoutFallbackAndValueNotSetProvidesCustomErrorMessageWhenSet(): void
    {
        $input = $this->input;
        $input->setRequired(true);
        $input->setErrorMessage('FAILED TO VALIDATE');

        $this->assertFalse(
            $input->isValid(),
            'isValid() should always return false when no fallback value is present, '
            . 'the input is required, and no data is set.'
        );
        $this->assertSame(['FAILED TO VALIDATE'], $input->getMessages());
    }

    public function testNotRequiredWithoutFallbackAndValueNotSetThenIsValid(): void
    {
        $input = $this->input;
        $input->setRequired(false);
        $input->setAllowEmpty(false);
        $input->setContinueIfEmpty(true);

        // Validator should not to be called
        $input->getValidatorChain()
            ->attach($this->createValidatorMock(null, null));
        $this->assertTrue(
            $input->isValid(),
            'isValid() should be return always true when is not required, and no data is set. Detail: '
            . json_encode($input->getMessages())
        );
        $this->assertEquals([], $input->getMessages(), 'getMessages() should be empty because the input is valid');
    }

    /**
     * @dataProvider emptyValueProvider
     * @param mixed $value
     */
    public function testNotEmptyValidatorNotInjectedIfContinueIfEmptyIsTrue($value): void
    {
        $input = $this->input;
        $input->setContinueIfEmpty(true);
        $input->setValue($value);
        $input->isValid();
        $validators = $input->getValidatorChain()
                                ->getValidators();
        $this->assertEmpty($validators);
    }

    public function testDefaultGetValue(): void
    {
        $this->assertNull($this->input->getValue());
    }

    public function testValueMayBeInjected(): void
    {
        $valueRaw = $this->getDummyValue();

        $this->input->setValue($valueRaw);
        $this->assertEquals($valueRaw, $this->input->getValue());
    }

    public function testRetrievingValueFiltersTheValue(): void
    {
        $valueRaw      = $this->getDummyValue();
        $valueFiltered = $this->getDummyValue(false);

        $filterChain = $this->createFilterChainMock([[$valueRaw, $valueFiltered]]);

        $this->input->setFilterChain($filterChain);
        $this->input->setValue($valueRaw);

        $this->assertSame($valueFiltered, $this->input->getValue());
    }

    public function testCanRetrieveRawValue(): void
    {
        $valueRaw = $this->getDummyValue();

        $filterChain = $this->createFilterChainMock();

        $this->input->setFilterChain($filterChain);
        $this->input->setValue($valueRaw);

        $this->assertEquals($valueRaw, $this->input->getRawValue());
    }

    public function testValidationOperatesOnFilteredValue(): void
    {
        $valueRaw      = $this->getDummyValue();
        $valueFiltered = $this->getDummyValue(false);

        $filterChain = $this->createFilterChainMock([[$valueRaw, $valueFiltered]]);

        $validatorChain = $this->createValidatorChainMock([[$valueFiltered, null, true]]);

        $this->input->setAllowEmpty(true);
        $this->input->setFilterChain($filterChain);
        $this->input->setValidatorChain($validatorChain);
        $this->input->setValue($valueRaw);

        $this->assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages())
        );
    }

    public function testBreakOnFailureFlagIsOffByDefault(): void
    {
        $this->assertFalse($this->input->breakOnFailure());
    }

    public function testBreakOnFailureFlagIsMutable(): void
    {
        $this->input->setBreakOnFailure(true);
        $this->assertTrue($this->input->breakOnFailure());
    }

    /**
     * @dataProvider emptyValueProvider
     * @param mixed $value
     */
    public function testNotEmptyValidatorAddedWhenIsValidIsCalled($value): void
    {
        $this->assertTrue($this->input->isRequired());
        $this->input->setValue($value);
        $validatorChain = $this->input->getValidatorChain();
        $this->assertEquals(0, count($validatorChain->getValidators()));

        $this->assertFalse($this->input->isValid());
        $messages = $this->input->getMessages();
        $this->assertArrayHasKey('isEmpty', $messages);
        $this->assertEquals(1, count($validatorChain->getValidators()));

        // Assert that NotEmpty validator wasn't added again
        $this->assertFalse($this->input->isValid());
        $this->assertEquals(1, count($validatorChain->getValidators()));
    }

    /**
     * @dataProvider emptyValueProvider
     * @param mixed $value
     */
    public function testRequiredNotEmptyValidatorNotAddedWhenOneExists($value): void
    {
        $this->input->setRequired(true);
        $this->input->setValue($value);

        $notEmptyMock = $this->createNonEmptyValidatorMock(false, $value);

        $validatorChain = $this->input->getValidatorChain();
        $validatorChain->prependValidator($notEmptyMock);
        $this->assertFalse($this->input->isValid());

        $validators = $validatorChain->getValidators();
        $this->assertEquals(1, count($validators));
        $this->assertEquals($notEmptyMock, $validators[0]['instance']);
    }

    /**
     * @dataProvider emptyValueProvider
     * @param mixed $valueRaw
     * @param mixed $valueFiltered
     */
    public function testDoNotInjectNotEmptyValidatorIfAnywhereInChain($valueRaw, $valueFiltered): void
    {
        $filterChain    = $this->createFilterChainMock([[$valueRaw, $valueFiltered]]);
        $validatorChain = $this->input->getValidatorChain();

        $this->input->setRequired(true);
        $this->input->setFilterChain($filterChain);
        $this->input->setValue($valueRaw);

        $notEmptyMock = $this->createNonEmptyValidatorMock(false, $valueFiltered);

        $validatorChain->attach($this->createValidatorMock(true));
        $validatorChain->attach($notEmptyMock);

        $this->assertFalse($this->input->isValid());

        $validators = $validatorChain->getValidators();
        $this->assertEquals(2, count($validators));
        $this->assertEquals($notEmptyMock, $validators[1]['instance']);
    }

    /**
     * @group 7448
     * @dataProvider isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider
     * @param mixed $value
     */
    public function testIsRequiredVsAllowEmptyVsContinueIfEmptyVsIsValid(
        bool $required,
        bool $allowEmpty,
        bool $continueIfEmpty,
        ValidatorInterface $validator,
        $value,
        bool $expectedIsValid,
        array $expectedMessages
    ): void {
        $this->input->setRequired($required);
        $this->input->setAllowEmpty($allowEmpty);
        $this->input->setContinueIfEmpty($continueIfEmpty);
        $this->input->getValidatorChain()
            ->attach($validator);
        $this->input->setValue($value);

        $this->assertEquals(
            $expectedIsValid,
            $this->input->isValid(),
            'isValid() value not match. Detail: ' . json_encode($this->input->getMessages())
        );
        $this->assertEquals($expectedMessages, $this->input->getMessages(), 'getMessages() value not match');
        $this->assertEquals($value, $this->input->getRawValue(), 'getRawValue() must return the value always');
        $this->assertEquals($value, $this->input->getValue(), 'getValue() must return the filtered value always');
    }

    /**
     * @dataProvider setValueProvider
     * @param mixed $value
     */
    public function testSetValuePutInputInTheDesiredState($value): void
    {
        $input = $this->input;
        $this->assertFalse($input->hasValue(), 'Input should not have value by default');

        $input->setValue($value);
        $this->assertTrue($input->hasValue(), "hasValue() didn't return true when value was set");
    }

    /**
     * @dataProvider setValueProvider
     * @param mixed $value
     */
    public function testResetValueReturnsInputValueToDefaultValue($value): void
    {
        $input         = $this->input;
        $originalInput = clone $input;
        $this->assertFalse($input->hasValue(), 'Input should not have value by default');

        $input->setValue($value);
        $this->assertTrue($input->hasValue(), "hasValue() didn't return true when value was set");

        $return = $input->resetValue();
        $this->assertSame($input, $return, 'resetValue() must return itself');
        $this->assertEquals($originalInput, $input, 'Input was not reset to the default value state');
    }

    public function testMerge(): void
    {
        $sourceRawValue = $this->getDummyValue();

        $source = $this->createInputInterfaceMock();
        $source->method('getName')->willReturn('bazInput');
        $source->method('getErrorMessage')->willReturn('bazErrorMessage');
        $source->method('breakOnFailure')->willReturn(true);
        $source->method('isRequired')->willReturn(true);
        $source->method('getRawValue')->willReturn($sourceRawValue);
        $source->method('getFilterChain')->willReturn($this->createFilterChainMock());
        $source->method('getValidatorChain')->willReturn($this->createValidatorChainMock());

        $targetFilterChain = $this->createFilterChainMock();
        $targetFilterChain->expects(TestCase::once())
            ->method('merge')
            ->with($source->getFilterChain());

        $targetValidatorChain = $this->createValidatorChainMock();
        $targetValidatorChain->expects(TestCase::once())
            ->method('merge')
            ->with($source->getValidatorChain());

        $target = $this->input;
        $target->setName('fooInput');
        $target->setErrorMessage('fooErrorMessage');
        $target->setBreakOnFailure(false);
        $target->setRequired(false);
        $target->setFilterChain($targetFilterChain);
        $target->setValidatorChain($targetValidatorChain);

        $return = $target->merge($source);
        $this->assertSame($target, $return, 'merge() must return it self');

        $this->assertEquals('bazInput', $target->getName(), 'getName() value not match');
        $this->assertEquals('bazErrorMessage', $target->getErrorMessage(), 'getErrorMessage() value not match');
        $this->assertTrue($target->breakOnFailure(), 'breakOnFailure() value not match');
        $this->assertTrue($target->isRequired(), 'isRequired() value not match');
        $this->assertEquals($sourceRawValue, $target->getRawValue(), 'getRawValue() value not match');
        $this->assertTrue($target->hasValue(), 'hasValue() value not match');
    }

    /**
     * Specific Input::merge extras
     */
    public function testInputMergeWithoutValues(): void
    {
        $source = new Input();
        $source->setContinueIfEmpty(true);
        $this->assertFalse($source->hasValue(), 'Source should not have a value');

        $target = $this->input;
        $target->setContinueIfEmpty(false);
        $this->assertFalse($target->hasValue(), 'Target should not have a value');

        $return = $target->merge($source);
        $this->assertSame($target, $return, 'merge() must return it self');

        $this->assertTrue($target->continueIfEmpty(), 'continueIfEmpty() value not match');
        $this->assertFalse($target->hasValue(), 'hasValue() value not match');
    }

    /**
     * Specific Input::merge extras
     */
    public function testInputMergeWithSourceValue(): void
    {
        $source = new Input();
        $source->setContinueIfEmpty(true);
        $source->setValue(['foo']);

        $target = $this->input;
        $target->setContinueIfEmpty(false);
        $this->assertFalse($target->hasValue(), 'Target should not have a value');

        $return = $target->merge($source);
        $this->assertSame($target, $return, 'merge() must return it self');

        $this->assertTrue($target->continueIfEmpty(), 'continueIfEmpty() value not match');
        $this->assertEquals(['foo'], $target->getRawValue(), 'getRawValue() value not match');
        $this->assertTrue($target->hasValue(), 'hasValue() value not match');
    }

    /**
     * Specific Input::merge extras
     */
    public function testInputMergeWithTargetValue(): void
    {
        $source = new Input();
        $source->setContinueIfEmpty(true);
        $this->assertFalse($source->hasValue(), 'Source should not have a value');

        $target = $this->input;
        $target->setContinueIfEmpty(false);
        $target->setValue(['foo']);

        $return = $target->merge($source);
        $this->assertSame($target, $return, 'merge() must return it self');

        $this->assertTrue($target->continueIfEmpty(), 'continueIfEmpty() value not match');
        $this->assertEquals(['foo'], $target->getRawValue(), 'getRawValue() value not match');
        $this->assertTrue($target->hasValue(), 'hasValue() value not match');
    }

    public function testNotEmptyMessageIsTranslated(): void
    {
        /** @var TranslatorInterface|MockObject $translator */
        $translator = $this->createMock(TranslatorInterface::class);
        AbstractValidator::setDefaultTranslator($translator);
        $notEmpty = new NotEmptyValidator();

        $translatedMessage = 'some translation';
        $translator->expects($this->atLeastOnce())
            ->method('translate')
            ->with($notEmpty->getMessageTemplates()[NotEmptyValidator::IS_EMPTY])
            ->willReturn($translatedMessage);

        $this->assertFalse($this->input->isValid());
        $messages = $this->input->getMessages();
        $this->assertArrayHasKey('isEmpty', $messages);
        $this->assertSame($translatedMessage, $messages['isEmpty']);
    }

    /**
     * @psalm-return array<string, array{
     *     0: bool,
     *     1: string,
     *     2: string,
     *     3: bool,
     *     4: string
     * }>
     */
    public function fallbackValueVsIsValidProvider(): array
    {
        $required = true;
        $isValid  = true;

        $originalValue = 'fooValue';
        $fallbackValue = 'fooFallbackValue';

        // phpcs:disable Generic.Files.LineLength.TooLong,WebimpressCodingStandard.Arrays.Format.SingleLineSpaceBefore
        return [
            // Description                                    => [$inputIsRequired, $fallbackValue, $originalValue, $isValid, $expectedValue]
            'Required: T, Input: Invalid. getValue: fallback' => [  $required, $fallbackValue, $originalValue, ! $isValid, $fallbackValue],
            'Required: T, Input: Valid. getValue: original'   => [  $required, $fallbackValue, $originalValue,   $isValid, $originalValue],
            'Required: F, Input: Invalid. getValue: fallback' => [! $required, $fallbackValue, $originalValue, ! $isValid, $fallbackValue],
            'Required: F, Input: Valid. getValue: original'   => [! $required, $fallbackValue, $originalValue,   $isValid, $originalValue],
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong,WebimpressCodingStandard.Arrays.Format.SingleLineSpaceBefore
    }

    /**
     * @psalm-return array<string, array{
     *     raw: bool|int|float|string|list<string>|object,
     *     filtered:  bool|int|float|string|list<string>|object
     * }>
     */
    public function setValueProvider(): array
    {
        $emptyValues = $this->emptyValueProvider();
        $mixedValues = $this->mixedValueProvider();

        $emptyValues = $emptyValues instanceof Iterator ? iterator_to_array($emptyValues) : $emptyValues;
        $mixedValues = $mixedValues instanceof Iterator ? iterator_to_array($mixedValues) : $mixedValues;

        Assert::isArray($emptyValues);
        Assert::isArray($mixedValues);

        return array_merge($emptyValues, $mixedValues);
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
    public function isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider(): iterable
    {
        $allValues = $this->setValueProvider();

        $emptyValues = $this->emptyValueProvider();
        $emptyValues = $emptyValues instanceof Iterator ? iterator_to_array($emptyValues) : $emptyValues;
        Assert::isArray($emptyValues);

        $nonEmptyValues = array_diff_key($allValues, $emptyValues);

        $isRequired = true;
        $aEmpty     = true;
        $cIEmpty    = true;
        $isValid    = true;

        $validatorMsg = ['FooValidator' => 'Invalid Value'];
        $notEmptyMsg  = ['isEmpty' => "Value is required and can't be empty"];

        $validatorNotCall = function ($value, $context = null) {
            return $this->createValidatorMock(null, $value, $context);
        };
        $validatorInvalid                              = function ($value, $context = null) use ($validatorMsg) {
            return $this->createValidatorMock(false, $value, $context, $validatorMsg);
        };
        $validatorValid = function ($value, $context = null) {
            return $this->createValidatorMock(true, $value, $context);
        };

        // phpcs:disable Generic.Files.LineLength.TooLong,WebimpressCodingStandard.Arrays.DoubleArrow.SpacesBefore,WebimpressCodingStandard.Arrays.Format.SingleLineSpaceBefore,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpacingAfterComma,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma,WebimpressCodingStandard.Arrays.Format.BlankLine,Generic.Formatting.MultipleStatementAlignment.NotSame
        $dataTemplates = [
            // Description => [$isRequired, $allowEmpty, $continueIfEmpty, $validator, [$values], $expectedIsValid, $expectedMessages]
            'Required: T; AEmpty: T; CIEmpty: T; Validator: T'                   => [  $isRequired,   $aEmpty,   $cIEmpty, $validatorValid  , $allValues     ,   $isValid, []],
            'Required: T; AEmpty: T; CIEmpty: T; Validator: F'                   => [  $isRequired,   $aEmpty,   $cIEmpty, $validatorInvalid, $allValues     , ! $isValid, $validatorMsg],

            'Required: T; AEmpty: T; CIEmpty: F; Validator: X, Value: Empty'     => [  $isRequired,   $aEmpty, ! $cIEmpty, $validatorNotCall, $emptyValues   ,   $isValid, []],
            'Required: T; AEmpty: T; CIEmpty: F; Validator: T, Value: Not Empty' => [  $isRequired,   $aEmpty, ! $cIEmpty, $validatorValid  , $nonEmptyValues,   $isValid, []],
            'Required: T; AEmpty: T; CIEmpty: F; Validator: F, Value: Not Empty' => [  $isRequired,   $aEmpty, ! $cIEmpty, $validatorInvalid, $nonEmptyValues, ! $isValid, $validatorMsg],

            'Required: T; AEmpty: F; CIEmpty: T; Validator: T'                   => [  $isRequired, ! $aEmpty,   $cIEmpty, $validatorValid  , $allValues     ,   $isValid, []],
            'Required: T; AEmpty: F; CIEmpty: T; Validator: F'                   => [  $isRequired, ! $aEmpty,   $cIEmpty, $validatorInvalid, $allValues     , ! $isValid, $validatorMsg],

            'Required: T; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty'     => [  $isRequired, ! $aEmpty, ! $cIEmpty, $validatorNotCall, $emptyValues   , ! $isValid, $notEmptyMsg],
            'Required: T; AEmpty: F; CIEmpty: F; Validator: T, Value: Not Empty' => [  $isRequired, ! $aEmpty, ! $cIEmpty, $validatorValid  , $nonEmptyValues,   $isValid, []],
            'Required: T; AEmpty: F; CIEmpty: F; Validator: F, Value: Not Empty' => [  $isRequired, ! $aEmpty, ! $cIEmpty, $validatorInvalid, $nonEmptyValues, ! $isValid, $validatorMsg],

            'Required: F; AEmpty: T; CIEmpty: T; Validator: T'                   => [! $isRequired,   $aEmpty,   $cIEmpty, $validatorValid  , $allValues     ,   $isValid, []],
            'Required: F; AEmpty: T; CIEmpty: T; Validator: F'                   => [! $isRequired,   $aEmpty,   $cIEmpty, $validatorInvalid, $allValues     , ! $isValid, $validatorMsg],

            'Required: F; AEmpty: T; CIEmpty: F; Validator: X, Value: Empty'     => [! $isRequired,   $aEmpty, ! $cIEmpty, $validatorNotCall, $emptyValues   ,   $isValid, []],
            'Required: F; AEmpty: T; CIEmpty: F; Validator: T, Value: Not Empty' => [! $isRequired,   $aEmpty, ! $cIEmpty, $validatorValid  , $nonEmptyValues,   $isValid, []],
            'Required: F; AEmpty: T; CIEmpty: F; Validator: F, Value: Not Empty' => [! $isRequired,   $aEmpty, ! $cIEmpty, $validatorInvalid, $nonEmptyValues, ! $isValid, $validatorMsg],

            'Required: F; AEmpty: F; CIEmpty: T; Validator: T'                   => [! $isRequired, ! $aEmpty,   $cIEmpty, $validatorValid  , $allValues     ,   $isValid, []],
            'Required: F; AEmpty: F; CIEmpty: T; Validator: F'                   => [! $isRequired, ! $aEmpty,   $cIEmpty, $validatorInvalid, $allValues     , ! $isValid, $validatorMsg],

            'Required: F; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty'     => [! $isRequired, ! $aEmpty, ! $cIEmpty, $validatorNotCall, $emptyValues   ,   $isValid, []],
            'Required: F; AEmpty: F; CIEmpty: F; Validator: T, Value: Not Empty' => [! $isRequired, ! $aEmpty, ! $cIEmpty, $validatorValid  , $nonEmptyValues,   $isValid, []],
            'Required: F; AEmpty: F; CIEmpty: F; Validator: F, Value: Not Empty' => [! $isRequired, ! $aEmpty, ! $cIEmpty, $validatorInvalid, $nonEmptyValues, ! $isValid, $validatorMsg],
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong,WebimpressCodingStandard.Arrays.DoubleArrow.SpacesBefore,WebimpressCodingStandard.Arrays.Format.SingleLineSpaceBefore,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpacingAfterComma,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma,WebimpressCodingStandard.Arrays.Format.BlankLine,Generic.Formatting.MultipleStatementAlignment.NotSame

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

    /**
     * @psalm-return iterable<string, array{
     *     raw: null|string|array,
     *     filtered: null|string|array
     * }>
     */
    public function emptyValueProvider(): iterable
    {
        return [
            // Description => [$value]
            'null' => [
                'raw'      => null,
                'filtered' => null,
            ],
            '""'   => [
                'raw'      => '',
                'filtered' => '',
            ],
            /* @todo Should these cases be tested?
            '"0"' => ['0'],
            '0' => [0],
            '0.0' => [0.0],
            'false' => [false],
             */
            '[]' => [
                'raw'      => [],
                'filtered' => [],
            ],
        ];
    }

    /**
     * @psalm-return array<string, array{
     *     raw: bool|int|float|string|list<string>|object,
     *     filtered: bool|int|float|string|list<string>|object
     * }>
     */
    public function mixedValueProvider(): array
    {
        return [
            // Description => [$value]
            '"0"' => [
                'raw'      => '0',
                'filtered' => '0',
            ],
            '0'   => [
                'raw'      => 0,
                'filtered' => 0,
            ],
            '0.0' => [
                'raw'      => 0.0,
                'filtered' => 0.0,
            ],
            /* @todo enable me
            'false' => [
                'raw' => false,
                'filtered' => false,
            ],
             */
            'php' => [
                'raw'      => 'php',
                'filtered' => 'php',
            ],
            /* @todo enable me
            'whitespace' => [
                'raw' => ' ',
                'filtered' => ' ',
            ],
             */
            '1'       => [
                'raw'      => 1,
                'filtered' => 1,
            ],
            '1.0'     => [
                'raw'      => 1.0,
                'filtered' => 1.0,
            ],
            'true'    => [
                'raw'      => true,
                'filtered' => true,
            ],
            '["php"]' => [
                'raw'      => ['php'],
                'filtered' => ['php'],
            ],
            'object'  => [
                'raw'      => new stdClass(),
                'filtered' => new stdClass(),
            ],
        ];
    }

    /**
     * @return InputInterface&MockObject
     */
    protected function createInputInterfaceMock()
    {
        /** @var InputInterface&MockObject $source */
        $source = $this->createMock(InputInterface::class);

        return $source;
    }

    /**
     * @param list<list<mixed>> $valueMap
     * @return FilterChain&MockObject
     */
    protected function createFilterChainMock(array $valueMap = [])
    {
        /** @var FilterChain&MockObject $filterChain */
        $filterChain = $this->createMock(FilterChain::class);

        $filterChain->method('filter')
            ->willReturnMap($valueMap);

        return $filterChain;
    }

    /**
     * @param list<list<mixed>> $valueMap
     * @param string[] $messages
     * @return ValidatorChain&MockObject
     */
    protected function createValidatorChainMock(array $valueMap = [], $messages = [])
    {
        /** @var ValidatorChain&MockObject $validatorChain */
        $validatorChain = $this->createMock(ValidatorChain::class);

        if (empty($valueMap)) {
            $validatorChain->expects($this->never())
                ->method('isValid');
        } else {
            $validatorChain->expects($this->atLeastOnce())
                ->method('isValid')
                ->willReturnMap($valueMap);
        }

        $validatorChain->method('getMessages')
            ->willReturn($messages);

        return $validatorChain;
    }

    /**
     * @param null|bool $isValid
     * @param mixed $value
     * @param mixed $context
     * @param string[] $messages
     * @return ValidatorInterface&MockObject
     */
    protected function createValidatorMock($isValid, $value = 'not-set', $context = null, $messages = [])
    {
        /** @var ValidatorInterface&MockObject $validator */
        $validator = $this->createMock(ValidatorInterface::class);

        if (($isValid === false) || ($isValid === true)) {
            $isValidMethod = $validator->expects($this->once())
                ->method('isValid')
                ->willReturn($isValid);
        } else {
            $isValidMethod = $validator->expects($this->never())
                ->method('isValid');
        }
        if ($value !== 'not-set') {
            $isValidMethod->with($value, $context);
        }

        $validator->method('getMessages')
            ->willReturn($messages);

        return $validator;
    }

    /**
     * @param bool $isValid
     * @param mixed $value
     * @param mixed $context
     * @return NotEmptyValidator&MockObject
     */
    protected function createNonEmptyValidatorMock($isValid, $value, $context = null)
    {
        /** @var NotEmptyValidator&MockObject $notEmptyMock */
        $notEmptyMock = $this->getMockBuilder(NotEmptyValidator::class)
            ->setMethods(['isValid'])
            ->getMock();
        $notEmptyMock->expects($this->once())
            ->method('isValid')
            ->with($value, $context)
            ->willReturn($isValid);

        return $notEmptyMock;
    }

    /** @return string */
    protected function getDummyValue(bool $raw = true)
    {
        return $raw ? 'foo' : 'filtered';
    }
}
