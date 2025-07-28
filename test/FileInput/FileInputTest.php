<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput;

use Laminas\Filter\FilterChain;
use Laminas\Filter\ToInt;
use Laminas\Filter\ToNull;
use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\FileInput\HttpServerFileInputHandler;
use Laminas\InputFilter\FileInput\PsrFileInputHandler;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputInterface;
use Laminas\Validator;
use Laminas\Validator\AbstractValidator;
use Laminas\Validator\File\UploadFile as UploadValidator;
use Laminas\Validator\NotEmpty as NotEmptyValidator;
use Laminas\Validator\NumberComparison;
use Laminas\Validator\Translator\TranslatorInterface;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use LaminasTest\InputFilter\TestAsset\UploadedFileInterfaceStub;
use LaminasTest\InputFilter\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

use function array_diff_key;
use function array_merge;
use function count;
use function iterator_to_array;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

/**
 * @psalm-suppress DeprecatedMethod
 * @psalm-suppress MixedArgument
 */
#[CoversClass(FileInput::class)]
#[CoversClass(HttpServerFileInputHandler::class)]
#[CoversClass(PsrFileInputHandler::class)]
final class FileInputTest extends TestCase
{
    protected FileInput $input;

    protected function setUp(): void
    {
        $this->input = new FileInput('foo');
        // Upload validator does not work in CLI test environment, disable
        $this->input->setAutoPrependUploadValidator(false);
    }

    protected function tearDown(): void
    {
        AbstractValidator::setDefaultTranslator();
    }

    #[DataProvider('validSingleValueProvider')]
    public function testRetrievingValueFiltersTheValueOnlyAfterValidating(mixed $raw, mixed $filtered): void
    {
        $this->input->setValue($raw);
        $this->input->setFilterChain(TestHelper::createFilterChainFixture($raw, $filtered));

        self::assertEquals($raw, $this->input->getValue());
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals($filtered, $this->input->getValue());
    }

    #[DAtaProvider('validMultiValueProvider')]
    public function testCanFilterArrayOfMultiFileData(array $raw, array $filtered): void
    {
        $this->input->resetValue();
        $this->input->setValue($raw);

        $map = [];
        for ($i = 0; $i < count($filtered); $i += 1) {
            $map[] = [$raw[$i], $filtered[$i]];
        }

        $this->input->setFilterChain(TestHelper::createFilterChainFixtureFromMap($map));

        self::assertEquals($raw, $this->input->getValue());
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals(
            $filtered,
            $this->input->getValue()
        );
    }

    #[DataProvider('validSingleValueProvider')]
    public function testCanRetrieveRawValue(mixed $raw, mixed $filtered): void
    {
        $this->input->setValue($raw);
        $this->input->setFilterChain(TestHelper::createFilterChainFixture($raw, $filtered));

        self::assertEquals($raw, $this->input->getRawValue());
    }

    #[DataProvider('invalidSingleValueProvider')]
    public function testValidationOperatesBeforeFiltering(mixed $raw, mixed $filtered): void
    {
        $this->input->setValue($raw);

        $this->input->setFilterChain(TestHelper::createFilterChainFixture($raw, $filtered));
        $this->input->setValidatorChain(TestHelper::createValidatorChain($raw, false));

        self::assertFalse($this->input->isValid());
        self::assertEquals($raw, $this->input->getValue());
    }

    public function testAutoPrependUploadValidatorIsOnByDefault(): void
    {
        $input = new FileInput('foo');
        self::assertTrue($input->getAutoPrependUploadValidator());
    }

    public function testUploadValidatorIsAddedWhenIsValidIsCalled(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue([
            'tmp_name' => __FILE__,
            'name'     => 'foo',
            'size'     => 1,
            'error'    => 0,
        ]);
        $validatorChain = $this->input->getValidatorChain();
        self::assertCount(0, $validatorChain->getValidators());

        self::assertFalse($this->input->isValid());
        $validators = $validatorChain->getValidators();
        self::assertCount(1, $validators);
        self::assertInstanceOf(Validator\File\UploadFile::class, $validators[0]['instance']);
    }

    public function testUploadValidatorIsNotAddedWhenIsValidIsCalled(): void
    {
        self::assertFalse($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue(['tmp_name' => 'bar']);
        $validatorChain = $this->input->getValidatorChain();
        self::assertCount(0, $validatorChain->getValidators());

        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertCount(0, $validatorChain->getValidators());
    }

    #[DataProvider('invalidSingleValueProvider')]
    public function testRequiredUploadValidatorValidatorNotAddedWhenOneExists(mixed $raw): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue($raw);

        $uploadValidator = new UploadValidator();

        $validatorChain = $this->input->getValidatorChain();
        $validatorChain->prependValidator($uploadValidator);
        self::assertFalse(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );

        $validators = $validatorChain->getValidators();
        self::assertCount(1, $validators);
        self::assertEquals($uploadValidator, $validators[0]['instance']);
    }

    public function testValidationsRunWithoutFileArrayDueToAjaxPost(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue([]);

        $expectedNormalizedValue = [
            'tmp_name' => '',
            'name'     => '',
            'size'     => 0,
            'type'     => '',
            'error'    => UPLOAD_ERR_NO_FILE,
        ];
        $this->input->setValidatorChain(TestHelper::createValidatorChain($expectedNormalizedValue, false));
        self::assertFalse($this->input->isValid());
    }

    public function testValidationsRunWithoutFileArrayIsSend(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue([]);
        $expectedNormalizedValue = [
            'tmp_name' => '',
            'name'     => '',
            'size'     => 0,
            'type'     => '',
            'error'    => UPLOAD_ERR_NO_FILE,
        ];
        $this->input->setValidatorChain(TestHelper::createValidatorChain($expectedNormalizedValue, false));
        self::assertFalse($this->input->isValid());
    }

    #[DataProvider('isEmptyProvider')]
    public function testIsEmpty(mixed $value, bool $expectedResult): void
    {
        self::assertEquals($expectedResult, $this->input->isEmptyFile($value));
    }

    public function testDefaultInjectedUploadValidatorRespectsRelease2Convention(): void
    {
        $input          = new FileInput('foo');
        $validatorChain = $input->getValidatorChain();
        $pluginManager  = $validatorChain->getPluginManager();
        $pluginManager->setInvokableClass(UploadValidator::class, TestAsset\FileUploadMock::class);
        $input->setValue([]);

        self::assertTrue($input->isValid());
    }

    /**
     * Specific FileInput::merge extras
     */
    public function testFileInputMerge(): void
    {
        $source = new FileInput();
        $source->setAutoPrependUploadValidator(true);

        $target = $this->input;
        $target->setAutoPrependUploadValidator(false);

        $return = $target->merge($source);
        self::assertSame($target, $return, 'merge() must return it self');

        self::assertTrue(
            $target->getAutoPrependUploadValidator(),
            'getAutoPrependUploadValidator() value not match'
        );
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
        $chain = TestHelper::createFilterChain();
        $this->input->setFilterChain($chain);
        self::assertSame($chain, $this->input->getFilterChain());
    }

    public function testCanInjectValidatorChain(): void
    {
        $chain = new ValidatorChain();
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
    public function testSetFallbackValue(mixed $raw): void
    {
        $input = $this->input;

        $return = $input->setFallbackValue($raw);
        self::assertSame($input, $return, 'setFallbackValue() must return it self');

        self::assertEquals($raw, $input->getFallbackValue(), 'getFallbackValue() value not match');
        self::assertTrue($input->hasFallback(), 'hasFallback() value not match');
    }

    #[DataProvider('setValueProvider')]
    public function testClearFallbackValue(mixed $raw): void
    {
        $input = $this->input;
        $input->setFallbackValue($raw);
        $input->clearFallbackValue();
        self::assertNull($input->getFallbackValue(), 'getFallbackValue() value not match');
        self::assertFalse($input->hasFallback(), 'hasFallback() value not match');
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
        $input = new FileInput();
        $input->setRequired(true);

        $customMessage = [
            NotEmptyValidator::IS_EMPTY => "Custom message",
        ];

        $input->getValidatorChain()->attach(new NotEmptyValidator(['messages' => $customMessage]));

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

    #[DataProvider('emptyValueProvider')]
    public function testNotEmptyValidatorNotInjectedIfContinueIfEmptyIsTrue(mixed $raw): void
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
        $valueRaw = ['tmp_name' => 'bar'];

        $this->input->setValue($valueRaw);
        self::assertEquals($valueRaw, $this->input->getValue());
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
    public function testDoNotInjectNotEmptyValidator(mixed $raw, mixed $filtered): void
    {
        $filterChain    = TestHelper::createFilterChainFixture($raw, $filtered);
        $validatorChain = $this->input->getValidatorChain();

        $this->input->setRequired(true);
        $this->input->setFilterChain($filterChain);
        $this->input->setValue($raw);

        $validatorChain->attach(TestHelper::createValidatorMock(true));

        self::assertTrue($this->input->isValid());

        $validators = $validatorChain->getValidators();
        self::assertEquals(1, count($validators));
    }

    #[DataProvider('isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider')]
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
    public function testSetValuePutInputInTheDesiredState(mixed $raw): void
    {
        $input = $this->input;
        self::assertFalse($input->hasValue(), 'Input should not have value by default');

        $input->setValue($raw);
        self::assertTrue($input->hasValue(), "hasValue() didn't return true when value was set");
    }

    #[DataProvider('setValueProvider')]
    public function testResetValueReturnsInputValueToDefaultValue(mixed $raw): void
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
        $a = new FileInput('a');
        $b = new FileInput('b');
        $a->merge($b);

        self::assertSame('b', $a->getName());
    }

    public function testMergingTwoInputsModifiesErrorMessage(): void
    {
        $a = new FileInput('a');
        $b = new FileInput('b');
        $b->setErrorMessage('Foo');
        $a->merge($b);

        self::assertSame('Foo', $a->getErrorMessage());
    }

    public function testMergingTwoInputsModifiesBreakOnFailureFlag(): void
    {
        $a = new FileInput('a');
        $a->setBreakOnFailure(false);
        $b = new FileInput('b');
        $b->setBreakOnFailure(true);
        $a->merge($b);

        self::assertTrue($a->breakOnFailure());
    }

    public function testMergingTwoInputsModifiesRequiredFlag(): void
    {
        $a = new FileInput('a');
        $a->setRequired(false);
        $b = new FileInput('b');
        $b->setRequired(true);
        $a->merge($b);

        self::assertTrue($a->isRequired());
    }

    public function testMergingTwoInputsModifiesAllowEmptyFlag(): void
    {
        $a = new FileInput('a');
        $a->setAllowEmpty(false);
        $b = new FileInput('b');
        $b->setAllowEmpty(true);
        $a->merge($b);

        self::assertTrue($a->allowEmpty());
    }

    /** @psalm-suppress InvalidArgument */
    public function testMergingTwoInputsCopiesTheValueIfSet(): void
    {
        $a = new FileInput('a');
        $a->setValue('a');
        $b = new FileInput('b');
        $b->setValue('b');
        $a->merge($b);

        self::assertSame('b', $a->getValue());
    }

    public function testThatMergingTwoInputsMergesTheFilterChain(): void
    {
        $filter1 = new ToInt();
        $filter2 = new ToNull();

        $a = new FileInput('a');
        $b = new FileInput('b');

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

        $a = new FileInput('a');
        $b = new FileInput('b');

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
        $sourceRawValue = ['tmp_name' => 'bar'];

        $source = $this->createMock(InputInterface::class);
        $source->method('getName')->willReturn('bazInput');
        $source->method('getErrorMessage')->willReturn('bazErrorMessage');
        $source->method('breakOnFailure')->willReturn(true);
        $source->method('isRequired')->willReturn(true);
        $source->method('getRawValue')->willReturn($sourceRawValue);
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
        self::assertEquals($sourceRawValue, $target->getRawValue(), 'getRawValue() value not match');
        self::assertTrue($target->hasValue(), 'hasValue() value not match');
    }

    /**
     * Specific Input::merge extras
     */
    public function testInputMergeWithoutValues(): void
    {
        $source = new FileInput();
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
        $source = new FileInput();
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
        $source = new FileInput();
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
        /**
         * @psalm-suppress DeprecatedInterface
         * @var TranslatorInterface&MockObject $translator
         */
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

    public function testUploadValidatorIsAddedDuringIsValidWhenAutoPrependUploadValidatorIsEnabled(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());

        $uploadedFile = new UploadedFileInterfaceStub(UPLOAD_ERR_NO_FILE);

        $this->input->setValue($uploadedFile);

        $validatorChain = $this->input->getValidatorChain();
        self::assertCount(0, $validatorChain->getValidators());

        self::assertFalse($this->input->isValid());
        $validators = $validatorChain->getValidators();
        self::assertCount(1, $validators);
        self::assertInstanceOf(Validator\File\UploadFile::class, $validators[0]['instance']);
    }

    public function testUploadValidatorIsNotAddedByDefaultDuringIsValidWhenAutoPrependUploadValidatorIsDisabled(): void
    {
        self::assertFalse($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());

        $uploadedFile = new UploadedFileInterfaceStub();

        $this->input->setValue($uploadedFile);
        $validatorChain = $this->input->getValidatorChain();
        self::assertCount(0, $validatorChain->getValidators());

        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertCount(0, $validatorChain->getValidators());
    }

    /**
     * Specific PsrFileInput::merge extras
     */
    public function testPsrFileInputMerge(): void
    {
        $source = new FileInput();
        $source->setAutoPrependUploadValidator(true);

        $target = $this->input;
        $target->setAutoPrependUploadValidator(false);

        $return = $target->merge($source);
        self::assertSame($target, $return, 'merge() must return it self');

        self::assertTrue(
            $target->getAutoPrependUploadValidator(),
            'getAutoPrependUploadValidator() value not match'
        );
    }

    /**
     * @return array<string, array{
     *      raw: array|UploadedFileInterface,
     *      filtered: array|UploadedFileInterface
     *  }>
     */
    public static function validSingleValueProvider(): array
    {
        return [
            'HttpServer single value' => [
                'raw'      => ['tmp_name' => 'foo', 'name' => 'foo', 'error' => UPLOAD_ERR_OK],
                'filtered' => ['tmp_name' => 'bar'],
            ],
            'Psr7 single value'       => [
                'raw'      => new UploadedFileInterfaceStub(),
                'filtered' => new UploadedFileInterfaceStub(),
            ],
        ];
    }

    /**
     * @return array<string, array{
     *      raw: array|UploadedFileInterface,
     *      filtered: array|UploadedFileInterface
     *  }>
     */
    public static function invalidSingleValueProvider(): array
    {
        return [
            'HttpServer invalid value' => [
                'raw'      => ['tmp_name' => 'foo', 'name' => 'foo', 'error' => UPLOAD_ERR_NO_FILE],
                'filtered' => ['tmp_name' => 'new foo'],
            ],
            'Psr7 invalid value'       => [
                'raw'      => new UploadedFileInterfaceStub(UPLOAD_ERR_NO_FILE),
                'filtered' => new UploadedFileInterfaceStub(),
            ],
        ];
    }

    /**
     * @psalm-return array<string, array{
     *     raw: mixed,
     *     filtered:  mixed
     * }>
     */
    public static function setValueProvider(): array
    {
        $httpServerUploadErrOk = [
            'tmp_name' => 'foo',
            'error'    => UPLOAD_ERR_OK,
        ];
        $psr7UploadErrOk       = new UploadedFileInterfaceStub(UPLOAD_ERR_OK);

        return array_merge(
            static::emptyValueProvider(),
            [
                'Single HttpServer file'    => [
                    'raw'      => $httpServerUploadErrOk,
                    'filtered' => $httpServerUploadErrOk,
                ],
                'Multiple HttpServer files' => [
                    'raw'      => [
                        $httpServerUploadErrOk,
                    ],
                    'filtered' => $httpServerUploadErrOk,
                ],
                'Single PSR7 file'          => [
                    'raw'      => $psr7UploadErrOk,
                    'filtered' => $psr7UploadErrOk,
                ],
                'Multiple PSR7 files'       => [
                    'raw'      => [
                        $psr7UploadErrOk,
                    ],
                    'filtered' => $psr7UploadErrOk,
                ],
            ]
        );
    }

    /**
     * @return array<string, array{
     *      raw: array,
     *      filtered: array
     *  }>
     */
    public static function validMultiValueProvider(): array
    {
        return [
            'HttpServer multi value' => [
                'raw'      => [
                    ['tmp_name' => 'foo', 'error' => UPLOAD_ERR_OK],
                    ['tmp_name' => 'bar', 'error' => UPLOAD_ERR_OK],
                    ['tmp_name' => 'baz', 'error' => UPLOAD_ERR_OK],
                ],
                'filtered' => [
                    ['tmp_name' => 'new foo'],
                    ['tmp_name' => 'new bar'],
                    ['tmp_name' => 'new baz'],
                ],
            ],
            'Psr7 multi value'       => [
                'raw'      => [
                    new UploadedFileInterfaceStub(),
                    new UploadedFileInterfaceStub(),
                    new UploadedFileInterfaceStub(),
                ],
                'filtered' => [
                    new UploadedFileInterfaceStub(),
                    new UploadedFileInterfaceStub(),
                    new UploadedFileInterfaceStub(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{
     *      string|array|UploadedFileInterface,
     *      bool
     *  }>
     */
    public static function isEmptyProvider(): array
    {
        return array_merge(
            ['not array' => ['file', true]],
            HttpServerFileInputHandlerTest::isEmptyProvider(),
            PsrFileInputHandlerTest::isEmptyProvider(),
        );
    }

    /**
     * @psalm-return array<string, array{
     *     0: bool,
     *     1: bool,
     *     2: bool,
     *     3: ValidatorInterface,
     *     4: mixed,
     *     5: bool,
     *     6: string[]
     * }>
     */
    public static function isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider(): array
    {
        $allValues   = static::setValueProvider();
        $emptyValues = static::emptyValueProvider();

        $nonEmptyValues = array_diff_key($allValues, $emptyValues);

        $validatorMsg = ['FooValidator' => 'Invalid Value'];

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
                /** @psalm-suppress MixedAssignment */
                $tmpTemplate[4] = $value['raw']; // expand value

                $dataSets[$dataTemplateDescription . ' / ' . $valueDescription] = $tmpTemplate;
            }
        }

        return $dataSets;
    }

    /**
     * @psalm-return array<string, array{raw: string|array|UploadedFileInterface, filtered:  mixed}>
     */
    public static function emptyValueProvider(): array
    {
        $raw = new UploadedFileInterfaceStub(UPLOAD_ERR_NO_FILE);

        return [
            'tmp_name'                        => [
                'raw'      => 'file',
                'filtered' => [
                    'tmp_name' => 'file',
                    'name'     => 'file',
                    'size'     => 0,
                    'type'     => '',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
            ],
            'Single empty HttpServer file'    => [
                'raw'      => [
                    'tmp_name' => '',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
                'filtered' => [
                    'tmp_name' => '',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
            ],
            'Multiple empty HttpServer files' => [
                'raw'      => [
                    [
                        'tmp_name' => 'foo',
                        'error'    => UPLOAD_ERR_NO_FILE,
                    ],
                ],
                'filtered' => [
                    'tmp_name' => 'foo',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
            ],
            'Single empty PSR7 file'          => [
                'raw'      => $raw,
                'filtered' => $raw,
            ],
            'Multiple empty PSR7 files'       => [
                'raw'      => [$raw],
                'filtered' => $raw,
            ],
        ];
    }
}
