<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput;

use Laminas\Filter\FilterChain;
use Laminas\Filter\ToInt;
use Laminas\Filter\ToNull;
use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\FileInput\HttpServerFileInputDecorator;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputInterface;
use Laminas\Validator;
use Laminas\Validator\AbstractValidator;
use Laminas\Validator\NotEmpty as NotEmptyValidator;
use Laminas\Validator\NumberComparison;
use Laminas\Validator\Translator\TranslatorInterface;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use LaminasTest\InputFilter\TestAsset\ValidatorStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
#[CoversClass(HttpServerFileInputDecorator::class)]
#[CoversClass(FileInput::class)]
final class HttpServerFileInputDecoratorTest extends TestCase
{
    protected HttpServerFileInputDecorator|FileInput $input;

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

    public function testRetrievingValueFiltersTheValueOnlyAfterValidating(): void
    {
        $value = ['tmp_name' => 'bar'];
        $this->input->setValue($value);

        $newValue = ['tmp_name' => 'foo'];
        $this->input->setFilterChain($this->createFilterChainMock([[$value, $newValue]]));

        self::assertEquals($value, $this->input->getValue());
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals($newValue, $this->input->getValue());
    }

    public function testCanFilterArrayOfMultiFileData(): void
    {
        $values = [
            ['tmp_name' => 'foo'],
            ['tmp_name' => 'bar'],
            ['tmp_name' => 'baz'],
        ];
        $this->input->setValue($values);

        $newValue      = ['tmp_name' => 'new'];
        $filteredValue = [$newValue, $newValue, $newValue];
        $this->input->setFilterChain($this->createFilterChainMock([
            [$values[0], $newValue],
            [$values[1], $newValue],
            [$values[2], $newValue],
        ]));

        self::assertEquals($values, $this->input->getValue());
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals(
            $filteredValue,
            $this->input->getValue()
        );
    }

    public function testCanRetrieveRawValue(): void
    {
        $value = ['tmp_name' => 'bar'];
        $this->input->setValue($value);

        $newValue = ['tmp_name' => 'new'];
        $this->input->setFilterChain($this->createFilterChainMock([[$value, $newValue]]));

        self::assertEquals($value, $this->input->getRawValue());
    }

    public function testValidationOperatesBeforeFiltering(): void
    {
        $badValue = [
            'tmp_name' => ' ' . __FILE__ . ' ',
            'name'     => 'foo',
            'size'     => 1,
            'error'    => 0,
        ];
        $this->input->setValue($badValue);

        $filteredValue = ['tmp_name' => 'new'];
        $this->input->setFilterChain($this->createFilterChainMock([[$badValue, $filteredValue]]));
        $this->input->setValidatorChain($this->createValidatorChain($badValue, false));

        self::assertFalse($this->input->isValid());
        self::assertEquals($badValue, $this->input->getValue());
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

    public function testRequiredUploadValidatorValidatorNotAddedWhenOneExists(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue(['tmp_name' => 'bar']);

        $uploadMock = $this->createMock(Validator\File\UploadFile::class);
        $uploadMock->expects(self::exactly(1))
                     ->method('isValid')
                     ->willReturn(true);

        $validatorChain = $this->input->getValidatorChain();
        $validatorChain->prependValidator($uploadMock);
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );

        $validators = $validatorChain->getValidators();
        self::assertCount(1, $validators);
        self::assertEquals($uploadMock, $validators[0]['instance']);
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
        $this->input->setValidatorChain($this->createValidatorChain($expectedNormalizedValue, false));
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
        $this->input->setValidatorChain($this->createValidatorChain($expectedNormalizedValue, false));
        self::assertFalse($this->input->isValid());
    }

    public function testIsEmptyFileNotArray(): void
    {
        $rawValue = 'file';
        self::assertTrue($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyFileUploadNoFile(): void
    {
        $rawValue = [
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_NO_FILE,
        ];
        self::assertTrue($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyFileOk(): void
    {
        $rawValue = [
            'tmp_name' => 'name',
            'error'    => UPLOAD_ERR_OK,
        ];
        self::assertFalse($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyMultiFileUploadNoFile(): void
    {
        $rawValue = [
            [
                'tmp_name' => 'foo',
                'error'    => UPLOAD_ERR_NO_FILE,
            ],
        ];
        self::assertTrue($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyFileMultiFileOk(): void
    {
        $rawValue = [
            [
                'tmp_name' => 'foo',
                'error'    => UPLOAD_ERR_OK,
            ],
            [
                'tmp_name' => 'bar',
                'error'    => UPLOAD_ERR_OK,
            ],
        ];
        self::assertFalse($this->input->isEmptyFile($rawValue));
    }

    public function testDefaultInjectedUploadValidatorRespectsRelease2Convention(): void
    {
        $input          = new FileInput('foo');
        $validatorChain = $input->getValidatorChain();
        $pluginManager  = $validatorChain->getPluginManager();
        $pluginManager->setInvokableClass('fileuploadfile', TestAsset\FileUploadMock::class);
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

        // phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSame
        $validatorNotCall = fn(mixed $value, array|null $context = null): ValidatorInterface =>
        self::createValidatorMock(null, $value, $context);
        $validatorInvalid = fn(mixed $value, array|null $context = null): ValidatorInterface =>
        self::createValidatorMock(false, $value, $context, $validatorMsg);
        $validatorValid = fn(mixed $value, array|null $context = null): ValidatorInterface =>
        self::createValidatorMock(true, $value, $context);

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
     * @psalm-return array<string, array{raw: string|array, filtered:  mixed}>
     */
    public static function emptyValueProvider(): array
    {
        return [
            'tmp_name' => [
                'raw'      => 'file',
                'filtered' => [
                    'tmp_name' => 'file',
                    'name'     => 'file',
                    'size'     => 0,
                    'type'     => '',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
            ],
            'single'   => [
                'raw'      => [
                    'tmp_name' => '',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
                'filtered' => [
                    'tmp_name' => '',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
            ],
            'multi'    => [
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
        ];
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
    public function testDoNotInjectNotEmptyValidatorIfAnywhereInChain(mixed $raw, mixed $filtered): void
    {
        $filterChain    = $this->createFilterChainMock([[$raw, $filtered]]);
        $validatorChain = $this->input->getValidatorChain();

        $this->input->setRequired(true);
        $this->input->setFilterChain($filterChain);
        $this->input->setValue($raw);

        $notEmptyMock = $this->createMock(NotEmptyValidator::class);
        $notEmptyMock->expects(self::once())
            ->method('isValid')
            ->with($filtered, null)
            ->willReturn(false);

        $notEmptyMock->method('getMessages')->willReturn([]);

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
        $validator2 = new NumberComparison(['min' => 1, 'max' => 5]);

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
        $sourceRawValue = ['tmp_name' => 'bar'];

        $source = $this->createMock(InputInterface::class);
        $source->method('getName')->willReturn('bazInput');
        $source->method('getErrorMessage')->willReturn('bazErrorMessage');
        $source->method('breakOnFailure')->willReturn(true);
        $source->method('isRequired')->willReturn(true);
        $source->method('getRawValue')->willReturn($sourceRawValue);
        $source->method('getFilterChain')->willReturn($this->createFilterChainMock());
        $source->method('getValidatorChain')->willReturn(new ValidatorChain());

        $targetFilterChain = $this->createFilterChainMock();
        $targetFilterChain->expects(TestCase::once())
            ->method('merge')
            ->with($source->getFilterChain());

        $target = $this->input;
        $target->setName('fooInput');
        $target->setErrorMessage('fooErrorMessage');
        $target->setBreakOnFailure(false);
        $target->setRequired(false);
        $target->setFilterChain($targetFilterChain);
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

    /**
     * @psalm-return array<string, array{
     *     raw: mixed,
     *     filtered:  mixed
     * }>
     */
    public static function setValueProvider(): array
    {
        $fooUploadErrOk = [
            'tmp_name' => 'foo',
            'error'    => UPLOAD_ERR_OK,
        ];

        return array_merge(
            static::emptyValueProvider(),
            [
                'single' => [
                    'raw'      => $fooUploadErrOk,
                    'filtered' => $fooUploadErrOk,
                ],
                'multi'  => [
                    'raw'      => [
                        $fooUploadErrOk,
                    ],
                    'filtered' => $fooUploadErrOk,
                ],
            ]
        );
    }

    /**
     * @param list<list<mixed>> $valueMap
     * @return FilterChain&MockObject
     */
    public function createFilterChainMock(array $valueMap = [])
    {
        /** @var FilterChain&MockObject $filterChain */
        $filterChain = $this->createMock(FilterChain::class);

        $filterChain->method('filter')
            ->willReturnMap($valueMap);

        return $filterChain;
    }

    protected function createValidatorChain(mixed $value, bool $isValid): ValidatorChain
    {
        return (new ValidatorChain())->attach(self::createValidatorMock($isValid, $value));
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
}
