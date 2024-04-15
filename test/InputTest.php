<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps

namespace LaminasTest\InputFilter;

use Iterator;
use Laminas\Filter\FilterChain;
use Laminas\Filter\ToInt;
use Laminas\Filter\ToNull;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputInterface;
use Laminas\Validator\AbstractValidator;
use Laminas\Validator\Between;
use Laminas\Validator\NotEmpty as NotEmptyValidator;
use Laminas\Validator\Translator\TranslatorInterface;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use LaminasTest\InputFilter\TestAsset\ValidatorStub;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webmozart\Assert\Assert;

use function array_diff_key;
use function array_merge;
use function count;
use function iterator_to_array;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * @psalm-suppress DeprecatedMethod
 */
class InputTest extends TestCase
{
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
        $chain = $this->createFilterChainMock();
        $this->input->setFilterChain($chain);
        self::assertSame($chain, $this->input->getFilterChain());
    }

    public function testCanInjectValidatorChain(): void
    {
        $chain = $this->createValidatorChainMock();
        $this->input->setValidatorChain($chain);
        self::assertSame($chain, $this->input->getValidatorChain());
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

    #[DataProvider('setValueProvider')]
    public function testSetFallbackValue(mixed $raw, mixed $filtered): void
    {
        $input = $this->input;

        $return = $input->setFallbackValue($raw);
        self::assertSame($input, $return, 'setFallbackValue() must return it self');

        self::assertEquals($raw, $input->getFallbackValue(), 'getFallbackValue() value not match');
        self::assertTrue($input->hasFallback(), 'hasFallback() value not match');
    }

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
     * @param string|string[] $fallbackValue
     * @param string|string[] $originalValue
     * @param string|string[] $expectedValue
     */
    #[DataProvider('fallbackValueVsIsValidProvider')]
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
     * @param string|string[] $fallbackValue
     */
    #[DataProvider('fallbackValueVsIsValidProvider')]
    public function testFallbackValueVsIsValidRulesWhenValueNotSet(bool $required, $fallbackValue): void
    {
        $expectedValue = $fallbackValue; // Should always return the fallback value

        $input = $this->input;
        $input->setContinueIfEmpty(true);

        $input->setRequired($required);
        $input->setValidatorChain($this->createValidatorChainMock());
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
        $input = new Input();
        $input->setRequired(true);

        $customMessage = [
            NotEmptyValidator::IS_EMPTY => "Custom message",
        ];

        $notEmpty = $this->createMock(NotEmptyValidator::class);
        $notEmpty->expects(self::once())
            ->method('getOption')
            ->with('messageTemplates')
            ->willReturn($customMessage);

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
            ->attach(self::createValidatorMock(null, null));
        self::assertTrue(
            $input->isValid(),
            'isValid() should be return always true when is not required, and no data is set. Detail: '
            . json_encode($input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals([], $input->getMessages(), 'getMessages() should be empty because the input is valid');
    }

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

    public function testDefaultGetValue(): void
    {
        self::assertNull($this->input->getValue());
    }

    public function testValueMayBeInjected(): void
    {
        $valueRaw = $this->getDummyValue();

        $this->input->setValue($valueRaw);
        self::assertEquals($valueRaw, $this->input->getValue());
    }

    public function testRetrievingValueFiltersTheValue(): void
    {
        $valueRaw      = $this->getDummyValue();
        $valueFiltered = $this->getDummyValue(false);

        $filterChain = $this->createFilterChainMock([[$valueRaw, $valueFiltered]]);

        $this->input->setFilterChain($filterChain);
        $this->input->setValue($valueRaw);

        self::assertSame($valueFiltered, $this->input->getValue());
    }

    public function testCanRetrieveRawValue(): void
    {
        $valueRaw = $this->getDummyValue();

        $filterChain = $this->createFilterChainMock();

        $this->input->setFilterChain($filterChain);
        $this->input->setValue($valueRaw);

        self::assertEquals($valueRaw, $this->input->getRawValue());
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

    #[DataProvider('emptyValueProvider')]
    public function testNotEmptyValidatorAddedWhenIsValidIsCalled(mixed $raw, mixed $filtered): void
    {
        self::assertTrue($this->input->isRequired());
        $this->input->setValue($raw);
        $validatorChain = $this->input->getValidatorChain();
        self::assertEquals(0, count($validatorChain->getValidators()));

        self::assertFalse($this->input->isValid());
        $messages = $this->input->getMessages();
        self::assertArrayHasKey('isEmpty', $messages);
        self::assertEquals(1, count($validatorChain->getValidators()));

        // Assert that NotEmpty validator wasn't added again
        self::assertFalse($this->input->isValid());
        self::assertEquals(1, count($validatorChain->getValidators()));
    }

    #[DataProvider('emptyValueProvider')]
    public function testRequiredNotEmptyValidatorNotAddedWhenOneExists(mixed $raw, mixed $filtered): void
    {
        $this->input->setRequired(true);
        $this->input->setValue($raw);

        $notEmptyMock = $this->createNonEmptyValidatorMock(false, $raw);

        $validatorChain = $this->input->getValidatorChain();
        $validatorChain->prependValidator($notEmptyMock);
        self::assertFalse($this->input->isValid());

        $validators = $validatorChain->getValidators();
        self::assertEquals(1, count($validators));
        self::assertEquals($notEmptyMock, $validators[0]['instance']);
    }

    #[DataProvider('emptyValueProvider')]
    public function testDoNotInjectNotEmptyValidatorIfAnywhereInChain(mixed $raw, mixed $filtered): void
    {
        $filterChain    = $this->createFilterChainMock([[$raw, $filtered]]);
        $validatorChain = $this->input->getValidatorChain();

        $this->input->setRequired(true);
        $this->input->setFilterChain($filterChain);
        $this->input->setValue($raw);

        $notEmptyMock = $this->createNonEmptyValidatorMock(false, $filtered);

        $validatorChain->attach(self::createValidatorMock(true));
        $validatorChain->attach($notEmptyMock);

        self::assertFalse($this->input->isValid());

        $validators = $validatorChain->getValidators();
        self::assertEquals(2, count($validators));
        self::assertEquals($notEmptyMock, $validators[1]['instance']);
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

    #[DataProvider('setValueProvider')]
    public function testSetValuePutInputInTheDesiredState(mixed $raw, mixed $filtered): void
    {
        $input = $this->input;
        self::assertFalse($input->hasValue(), 'Input should not have value by default');

        $input->setValue($raw);
        self::assertTrue($input->hasValue(), "hasValue() didn't return true when value was set");
    }

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
        $a = new Input('a');
        $b = new Input('b');
        $a->merge($b);

        self::assertSame('b', $a->getName());
    }

    public function testMergingTwoInputsModifiesErrorMessage(): void
    {
        $a = new Input('a');
        $b = new Input('b');
        $b->setErrorMessage('Foo');
        $a->merge($b);

        self::assertSame('Foo', $a->getErrorMessage());
    }

    public function testMergingTwoInputsModifiesBreakOnFailureFlag(): void
    {
        $a = new Input('a');
        $a->setBreakOnFailure(false);
        $b = new Input('b');
        $b->setBreakOnFailure(true);
        $a->merge($b);

        self::assertTrue($a->breakOnFailure());
    }

    public function testMergingTwoInputsModifiesRequiredFlag(): void
    {
        $a = new Input('a');
        $a->setRequired(false);
        $b = new Input('b');
        $b->setRequired(true);
        $a->merge($b);

        self::assertTrue($a->isRequired());
    }

    public function testMergingTwoInputsModifiesAllowEmptyFlag(): void
    {
        $a = new Input('a');
        $a->setAllowEmpty(false);
        $b = new Input('b');
        $b->setAllowEmpty(true);
        $a->merge($b);

        self::assertTrue($a->allowEmpty());
    }

    public function testMergingTwoInputsCopiesTheValueIfSet(): void
    {
        $a = new Input('a');
        $a->setValue('a');
        $b = new Input('b');
        $b->setValue('b');
        $a->merge($b);

        self::assertSame('b', $a->getValue());
    }

    public function testThatMergingTwoInputsMergesTheFilterChain(): void
    {
        $filter1 = new ToInt();
        $filter2 = new ToNull();

        $a = new Input('a');
        $b = new Input('b');

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
        $validator2 = new Between(['min' => 1, 'max' => 5]);

        $a = new Input('a');
        $b = new Input('b');

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
        self::assertSame($target, $return, 'merge() must return it self');

        self::assertEquals('bazInput', $target->getName(), 'getName() value not match');
        self::assertEquals('bazErrorMessage', $target->getErrorMessage(), 'getErrorMessage() value not match');
        self::assertTrue($target->breakOnFailure(), 'breakOnFailure() value not match');
        self::assertTrue($target->isRequired(), 'isRequired() value not match');
        self::assertEquals($sourceRawValue, $target->getRawValue(), 'getRawValue() value not match');
        self::assertTrue($target->hasValue(), 'hasValue() value not match');
    }

    /**
     * Specific Input::merge extras
     */
    public function testInputMergeWithoutValues(): void
    {
        $source = new Input();
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
        $source = new Input();
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
        $source = new Input();
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
        /** @var TranslatorInterface|MockObject $translator */
        $translator = $this->createMock(TranslatorInterface::class);
        AbstractValidator::setDefaultTranslator($translator);
        $notEmpty = new NotEmptyValidator();

        $translatedMessage = 'some translation';
        $translator->expects(self::atLeastOnce())
            ->method('translate')
            ->with($notEmpty->getMessageTemplates()[NotEmptyValidator::IS_EMPTY])
            ->willReturn($translatedMessage);

        self::assertFalse($this->input->isValid());
        $messages = $this->input->getMessages();
        self::assertArrayHasKey('isEmpty', $messages);
        self::assertSame($translatedMessage, $messages['isEmpty']);
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
    public static function fallbackValueVsIsValidProvider(): array
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
    public static function setValueProvider(): array
    {
        $emptyValues = static::emptyValueProvider();
        $mixedValues = static::mixedValueProvider();

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
    public static function isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider(): iterable
    {
        $allValues = static::setValueProvider();

        $emptyValues = static::emptyValueProvider();
        $emptyValues = $emptyValues instanceof Iterator ? iterator_to_array($emptyValues) : $emptyValues;
        Assert::isArray($emptyValues);

        $nonEmptyValues = array_diff_key($allValues, $emptyValues);

        $isRequired = true;
        $aEmpty     = true;
        $cIEmpty    = true;
        $isValid    = true;

        $validatorMsg = ['FooValidator' => 'Invalid Value'];
        $notEmptyMsg  = ['isEmpty' => "Value is required and can't be empty"];

        // phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSame
        $validatorNotCall = fn(mixed $value, array|null $context = null): ValidatorInterface =>
            self::createValidatorMock(null, $value, $context);
        $validatorInvalid = fn(mixed $value, array|null $context = null): ValidatorInterface =>
            self::createValidatorMock(false, $value, $context, $validatorMsg);
        $validatorValid = fn(mixed $value, array|null $context = null): ValidatorInterface =>
            self::createValidatorMock(true, $value, $context);

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
    public static function emptyValueProvider(): iterable
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
     *     raw: mixed,
     *     filtered: mixed,
     * }>
     */
    public static function mixedValueProvider(): array
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

    protected function createInputInterfaceMock(): InputInterface&MockObject
    {
        return $this->createMock(InputInterface::class);
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
            $validatorChain->expects(self::never())
                ->method('isValid');
        } else {
            $validatorChain->expects(self::atLeastOnce())
                ->method('isValid')
                ->willReturnMap($valueMap);
        }

        $validatorChain->method('getMessages')
            ->willReturn($messages);

        return $validatorChain;
    }

    /** @param array<string, string> $messages */
    protected static function createValidatorMock(
        bool|null $isValid,
        mixed $value = 'not-set',
        array|null $context = null,
        array $messages = []
    ): ValidatorInterface {
        return new ValidatorStub($isValid, $value, $context, $messages);
    }

    protected function createNonEmptyValidatorMock(
        bool $isValid,
        mixed $value,
        mixed $context = null
    ): NotEmptyValidator&MockObject {
        $notEmptyMock = $this->createMock(NotEmptyValidator::class);
        $notEmptyMock->expects(self::once())
            ->method('isValid')
            ->with($value, $context)
            ->willReturn($isValid);

        if ($isValid === false) {
            $notEmptyMock->method('getMessages')->willReturn([]);
        }

        return $notEmptyMock;
    }

    /** @return string */
    protected function getDummyValue(bool $raw = true)
    {
        return $raw ? 'foo' : 'filtered';
    }
}
