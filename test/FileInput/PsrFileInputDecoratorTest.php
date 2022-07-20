<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput;

use Generator;
use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\FileInput\PsrFileInputDecorator;
use Laminas\Validator;
use LaminasTest\InputFilter\InputTest;
use Psr\Http\Message\UploadedFileInterface;

use function count;
use function in_array;
use function json_encode;

use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

/**
 * @covers \Laminas\InputFilter\FileInput\PsrFileInputDecorator
 * @covers \Laminas\InputFilter\FileInput
 */
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
        $this->markTestSkipped('Test is not enabled in PsrFileInputTest');
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

        $this->assertEquals($upload, $this->input->getValue());
        $this->assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages())
        );
        $this->assertEquals($filteredUpload, $this->input->getValue());
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

        $this->assertEquals($values, $this->input->getValue());
        $this->assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages())
        );
        $this->assertEquals(
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

        $this->assertEquals($value, $this->input->getRawValue());
    }

    public function testValidationOperatesOnFilteredValue(): void
    {
        $this->markTestSkipped('Test is not enabled in PsrFileInputTest');
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

        $this->assertFalse($this->input->isValid());
        $this->assertEquals($badValue, $this->input->getValue());
    }

    public function testAutoPrependUploadValidatorIsOnByDefault(): void
    {
        $input = new FileInput('foo');
        $this->assertTrue($input->getAutoPrependUploadValidator());
    }

    public function testUploadValidatorIsAddedDuringIsValidWhenAutoPrependUploadValidatorIsEnabled(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        $this->assertTrue($this->input->getAutoPrependUploadValidator());
        $this->assertTrue($this->input->isRequired());

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_CANT_WRITE);

        $this->input->setValue($uploadedFile);

        $validatorChain = $this->input->getValidatorChain();
        $this->assertCount(0, $validatorChain->getValidators());

        $this->assertFalse($this->input->isValid());
        $validators = $validatorChain->getValidators();
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(Validator\File\UploadFile::class, $validators[0]['instance']);
    }

    public function testUploadValidatorIsNotAddedByDefaultDuringIsValidWhenAutoPrependUploadValidatorIsDisabled(): void
    {
        $this->assertFalse($this->input->getAutoPrependUploadValidator());
        $this->assertTrue($this->input->isRequired());

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_OK);

        $this->input->setValue($uploadedFile);
        $validatorChain = $this->input->getValidatorChain();
        $this->assertEquals(0, count($validatorChain->getValidators()));

        $this->assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages())
        );
        $this->assertEquals(0, count($validatorChain->getValidators()));
    }

    public function testRequiredUploadValidatorValidatorNotAddedWhenOneExists(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        $this->assertTrue($this->input->getAutoPrependUploadValidator());
        $this->assertTrue($this->input->isRequired());

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
        $this->assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages())
        );

        $validators = $validatorChain->getValidators();
        $this->assertEquals(1, count($validators));
        $this->assertEquals($validator, $validators[0]['instance']);
    }

    /** @param mixed $value */
    public function testNotEmptyValidatorAddedWhenIsValidIsCalled($value = null): void
    {
        $this->markTestSkipped('Test is not enabled in PsrFileInputTest');
    }

    /** @param mixed $value */
    public function testRequiredNotEmptyValidatorNotAddedWhenOneExists($value = null): void
    {
        $this->markTestSkipped('Test is not enabled in PsrFileInputTest');
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
        $this->markTestSkipped('Input::setFallbackValue is not implemented on PsrFileInput');
    }

    /** @param null|string|string[] $fallbackValue */
    public function testFallbackValueVsIsValidRulesWhenValueNotSet(
        ?bool $required = null,
        $fallbackValue = null
    ): void {
        $this->markTestSkipped('Input::setFallbackValue is not implemented on PsrFileInput');
    }

    public function testIsEmptyFileUploadNoFile(): void
    {
        $upload = $this->createMock(UploadedFileInterface::class);
        $upload->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_NO_FILE);
        $this->assertTrue($this->input->isEmptyFile($upload));
    }

    public function testIsEmptyFileOk(): void
    {
        $upload = $this->createMock(UploadedFileInterface::class);
        $upload->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_OK);
        $this->assertFalse($this->input->isEmptyFile($upload));
    }

    public function testIsEmptyMultiFileUploadNoFile(): void
    {
        $upload = $this->createMock(UploadedFileInterface::class);
        $upload->expects(self::atLeast(1))
            ->method('getError')
            ->willReturn(UPLOAD_ERR_NO_FILE);

        $rawValue = [$upload];

        $this->assertTrue($this->input->isEmptyFile($rawValue));
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

        $this->assertFalse($this->input->isEmptyFile($rawValue));
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
        $this->assertSame($target, $return, 'merge() must return it self');

        $this->assertEquals(
            true,
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
    public function isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider(): iterable
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
    public function emptyValueProvider(): iterable
    {
        foreach (['single', 'multi'] as $type) {
            $raw = $this->createMock(UploadedFileInterface::class);
            $raw->expects(self::atLeast(1))
                ->method('getError')
                ->willReturn(UPLOAD_ERR_NO_FILE);

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
    public function mixedValueProvider(): array
    {
        $fooUploadErrOk = $this->createMock(UploadedFileInterface::class);
        $fooUploadErrOk->method('getError')->willReturn(UPLOAD_ERR_OK);

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
