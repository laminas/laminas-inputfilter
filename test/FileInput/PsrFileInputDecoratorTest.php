<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput;

use Generator;
use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\FileInput\PsrFileInputDecorator;
use Laminas\Validator;
use LaminasTest\InputFilter\InputTest;
use LaminasTest\InputFilter\TestAsset\UploadedFileInterfaceStub;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\UploadedFileInterface;

use function in_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

#[CoversClass(PsrFileInputDecorator::class)]
#[CoversClass(FileInput::class)]
class PsrFileInputDecoratorTest extends InputTest
{
    /** @var PsrFileInputDecorator */
    protected $input;

    protected function setUp(): void
    {
        $this->input = new FileInput('foo');
        // Upload validator does not work in CLI test environment, disable
        $this->input->setAutoPrependUploadValidator(false);
    }

    public function testRetrievingValueFiltersTheValue(): void
    {
        self::markTestSkipped('Test is not enabled in PsrFileInputTest');
    }

    public function testRetrievingValueFiltersTheValueOnlyAfterValidating(): void
    {
        $upload = $this->createMock(UploadedFileInterface::class);
        $upload->expects(self::once())
            ->method('getError')
            ->willReturn(UPLOAD_ERR_OK);

        $this->input->setValue($upload);

        $filteredUpload = $this->createMock(UploadedFileInterface::class);

        $this->input->setFilterChain($this->createFilterChainMock([
            [
                $upload,
                $filteredUpload,
            ],
        ]));

        self::assertEquals($upload, $this->input->getValue());
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals($filteredUpload, $this->input->getValue());
    }

    public function testCanFilterArrayOfMultiFileData(): void
    {
        $values = [];
        for ($i = 0; $i < 3; $i += 1) {
            $upload = $this->createMock(UploadedFileInterface::class);
            $upload->method('getError')
                ->willReturn(UPLOAD_ERR_OK);
            $values[] = $upload;
        }

        $this->input->setValue($values);

        $filteredValues = [];
        for ($i = 0; $i < 3; $i += 1) {
            $filteredValues[] = $this->createMock(UploadedFileInterface::class);
        }

        $this->input->setFilterChain($this->createFilterChainMock([
            [$values[0], $filteredValues[0]],
            [$values[1], $filteredValues[1]],
            [$values[2], $filteredValues[2]],
        ]));

        self::assertEquals($values, $this->input->getValue());
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals(
            $filteredValues,
            $this->input->getValue()
        );
    }

    public function testCanRetrieveRawValue(): void
    {
        $value = $this->createMock(UploadedFileInterface::class);
        $value->expects(self::never())->method('getError');

        $this->input->setValue($value);

        $filteredValue = $this->createMock(UploadedFileInterface::class);
        $this->input->setFilterChain($this->createFilterChainMock([[$value, $filteredValue]]));

        self::assertEquals($value, $this->input->getRawValue());
    }

    public function testValidationOperatesOnFilteredValue(): void
    {
        self::markTestSkipped('Test is not enabled in PsrFileInputTest');
    }

    public function testValidationOperatesBeforeFiltering(): void
    {
        $badValue = $this->createMock(UploadedFileInterface::class);
        $badValue->expects(self::once())
            ->method('getError')
            ->willReturn(UPLOAD_ERR_NO_FILE);
        $filteredValue = $this->createMock(UploadedFileInterface::class);

        $this->input->setValue($badValue);

        $this->input->setFilterChain($this->createFilterChainMock([[$badValue, $filteredValue]]));
        $this->input->setValidatorChain($this->createValidatorChainMock([[$badValue, null, false]]));

        self::assertFalse($this->input->isValid());
        self::assertEquals($badValue, $this->input->getValue());
    }

    public function testAutoPrependUploadValidatorIsOnByDefault(): void
    {
        $input = new FileInput('foo');
        self::assertTrue($input->getAutoPrependUploadValidator());
    }

    public function testUploadValidatorIsAddedDuringIsValidWhenAutoPrependUploadValidatorIsEnabled(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_CANT_WRITE);

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

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_OK);

        $this->input->setValue($uploadedFile);
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

        $upload = $this->createMock(UploadedFileInterface::class);
        $upload->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_OK);

        $this->input->setValue($upload);

        $validator = $this->createMock(Validator\File\UploadFile::class);
        $validator->expects(self::once())
            ->method('isValid')
            ->with($upload)
            ->willReturn(true);

        $validatorChain = $this->input->getValidatorChain();
        $validatorChain->prependValidator($validator);
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );

        $validators = $validatorChain->getValidators();
        self::assertCount(1, $validators);
        self::assertEquals($validator, $validators[0]['instance']);
    }

    /** @param mixed $value */
    public function testNotEmptyValidatorAddedWhenIsValidIsCalled($value = null): void
    {
        self::markTestSkipped('Test is not enabled in PsrFileInputTest');
    }

    /** @param mixed $value */
    public function testRequiredNotEmptyValidatorNotAddedWhenOneExists($value = null): void
    {
        self::markTestSkipped('Test is not enabled in PsrFileInputTest');
    }

    /**
     * @param null|string|string[] $fallbackValue
     * @param null|string|string[] $originalValue
     * @param null|string|string[] $expectedValue
     */
    public function testFallbackValueVsIsValidRules(
        ?bool $required = null,
        $fallbackValue = null,
        $originalValue = null,
        ?bool $isValid = null,
        $expectedValue = null
    ): void {
        self::markTestSkipped('Input::setFallbackValue is not implemented on PsrFileInput');
    }

    /** @param null|string|string[] $fallbackValue */
    public function testFallbackValueVsIsValidRulesWhenValueNotSet(
        ?bool $required = null,
        $fallbackValue = null
    ): void {
        self::markTestSkipped('Input::setFallbackValue is not implemented on PsrFileInput');
    }

    public function testIsEmptyFileUploadNoFile(): void
    {
        $upload = $this->createMock(UploadedFileInterface::class);
        $upload->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_NO_FILE);
        self::assertTrue($this->input->isEmptyFile($upload));
    }

    public function testIsEmptyFileOk(): void
    {
        $upload = $this->createMock(UploadedFileInterface::class);
        $upload->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_OK);
        self::assertFalse($this->input->isEmptyFile($upload));
    }

    public function testIsEmptyMultiFileUploadNoFile(): void
    {
        $upload = $this->createMock(UploadedFileInterface::class);
        $upload->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_NO_FILE);

        $rawValue = [$upload];

        self::assertTrue($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyFileMultiFileOk(): void
    {
        $rawValue = [];
        for ($i = 0; $i < 2; $i += 1) {
            $upload = $this->createMock(UploadedFileInterface::class);
            $upload->expects(self::any())
                ->method('getError')
                ->willReturn(UPLOAD_ERR_OK);
            $rawValue[] = $upload;
        }

        self::assertFalse($this->input->isEmptyFile($rawValue));
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
     * @psalm-return iterable<string, array{
     *     0: bool,
     *     1: bool,
     *     2: bool,
     *     3: callable,
     *     4: mixed,
     *     5: bool,
     *     6: string[]
     * }>
     */
    public static function isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider(): iterable
    {
        $generator = parent::isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider();
        if ($generator instanceof Generator) {
            $generator = clone $generator;
            $generator->rewind();
        }

        $toSkip = [
            'Required: T; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty / tmp_name',
            'Required: T; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty / single',
            'Required: T; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty / multi',
        ];

        foreach ($generator as $name => $data) {
            if (in_array($name, $toSkip, true)) {
                continue;
            }
            yield $name => $data;
        }
    }

    /**
     * @psalm-return iterable<string, array{
     *     raw: UploadedFileInterface|list<UploadedFileInterface>,
     *     filtered: UploadedFileInterface
     * }>
     */
    public static function emptyValueProvider(): iterable
    {
        foreach (['single', 'multi'] as $type) {
            $raw = new UploadedFileInterfaceStub(UPLOAD_ERR_NO_FILE);
            yield $type => [
                'raw'      => $type === 'multi'
                    ? [$raw]
                    : $raw,
                'filtered' => $raw,
            ];
        }
    }

    /**
     * @psalm-return array<string, array{
     *     raw: UploadedFileInterface|list<UploadedFileInterface>,
     *     filtered: UploadedFileInterface
     * }>
     */
    public static function mixedValueProvider(): array
    {
        $fooUploadErrOk = new UploadedFileInterfaceStub(UPLOAD_ERR_OK);

        return [
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
        ];
    }

    /** @return UploadedFileInterface */
    protected function getDummyValue(bool $raw = true)
    {
        $upload = $this->createMock(UploadedFileInterface::class);
        $upload->method('getError')->willReturn(UPLOAD_ERR_OK);
        return $upload;
    }
}
