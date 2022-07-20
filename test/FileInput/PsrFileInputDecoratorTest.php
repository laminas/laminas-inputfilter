<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput;

use Generator;
use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\FileInput\PsrFileInputDecorator;
use Laminas\Validator;
use LaminasTest\InputFilter\InputTest;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
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
    use ProphecyTrait;

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
        $upload = $this->prophesize(UploadedFileInterface::class);
        $upload->getError()->willReturn(UPLOAD_ERR_OK);

        $this->input->setValue($upload->reveal());

        $filteredUpload = $this->prophesize(UploadedFileInterface::class);

        $this->input->setFilterChain($this->createFilterChainMock([
            [
                $upload->reveal(),
                $filteredUpload->reveal(),
            ],
        ]));

        $this->assertEquals($upload->reveal(), $this->input->getValue());
        $this->assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages())
        );
        $this->assertEquals($filteredUpload->reveal(), $this->input->getValue());
    }

    public function testCanFilterArrayOfMultiFileData(): void
    {
        $values = [];
        for ($i = 0; $i < 3; $i += 1) {
            $upload = $this->prophesize(UploadedFileInterface::class);
            $upload->getError()->willReturn(UPLOAD_ERR_OK);
            $values[] = $upload->reveal();
        }

        $this->input->setValue($values);

        $filteredValues = [];
        for ($i = 0; $i < 3; $i += 1) {
            $upload           = $this->prophesize(UploadedFileInterface::class);
            $filteredValues[] = $upload->reveal();
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
        $value = $this->prophesize(UploadedFileInterface::class);
        $value->getError()->shouldNotBeCalled();

        $this->input->setValue($value->reveal());

        $filteredValue = $this->prophesize(UploadedFileInterface::class)->reveal();
        $this->input->setFilterChain($this->createFilterChainMock([[$value->reveal(), $filteredValue]]));

        $this->assertEquals($value->reveal(), $this->input->getRawValue());
    }

    public function testValidationOperatesOnFilteredValue(): void
    {
        $this->markTestSkipped('Test is not enabled in PsrFileInputTest');
    }

    public function testValidationOperatesBeforeFiltering(): void
    {
        $badValue = $this->prophesize(UploadedFileInterface::class);
        $badValue->getError()->willReturn(UPLOAD_ERR_NO_FILE);
        $filteredValue = $this->prophesize(UploadedFileInterface::class)->reveal();

        $this->input->setValue($badValue->reveal());

        $this->input->setFilterChain($this->createFilterChainMock([[$badValue->reveal(), $filteredValue]]));
        $this->input->setValidatorChain($this->createValidatorChainMock([[$badValue->reveal(), null, false]]));

        $this->assertFalse($this->input->isValid());
        $this->assertEquals($badValue->reveal(), $this->input->getValue());
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

        $uploadedFile = $this->prophesize(UploadedFileInterface::class);
        $uploadedFile->getError()->willReturn(UPLOAD_ERR_CANT_WRITE);

        $this->input->setValue($uploadedFile->reveal());

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

        $uploadedFile = $this->prophesize(UploadedFileInterface::class);
        $uploadedFile->getError()->willReturn(UPLOAD_ERR_OK);

        $this->input->setValue($uploadedFile->reveal());
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

        $upload = $this->prophesize(UploadedFileInterface::class);
        $upload->getError()->willReturn(UPLOAD_ERR_OK);

        $this->input->setValue($upload->reveal());

        $validator = $this->prophesize(Validator\File\UploadFile::class);
        $validator
            ->isValid(Argument::that([$upload, 'reveal']), null)
            ->willReturn(true)
            ->shouldBeCalledTimes(1);

        $validatorChain = $this->input->getValidatorChain();
        $validatorChain->prependValidator($validator->reveal());
        $this->assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages())
        );

        $validators = $validatorChain->getValidators();
        $this->assertEquals(1, count($validators));
        $this->assertEquals($validator->reveal(), $validators[0]['instance']);
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
        $upload = $this->prophesize(UploadedFileInterface::class);
        $upload->getError()->willReturn(UPLOAD_ERR_NO_FILE);
        $this->assertTrue($this->input->isEmptyFile($upload->reveal()));
    }

    public function testIsEmptyFileOk(): void
    {
        $upload = $this->prophesize(UploadedFileInterface::class);
        $upload->getError()->willReturn(UPLOAD_ERR_OK);
        $this->assertFalse($this->input->isEmptyFile($upload->reveal()));
    }

    public function testIsEmptyMultiFileUploadNoFile(): void
    {
        $upload = $this->prophesize(UploadedFileInterface::class);
        $upload->getError()->willReturn(UPLOAD_ERR_NO_FILE);

        $rawValue = [$upload->reveal()];

        $this->assertTrue($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyFileMultiFileOk(): void
    {
        $rawValue = [];
        for ($i = 0; $i < 2; $i += 1) {
            $upload = $this->prophesize(UploadedFileInterface::class);
            $upload->getError()->willReturn(UPLOAD_ERR_OK);
            $rawValue[] = $upload->reveal();
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
            $raw = $this->prophesize(UploadedFileInterface::class);
            $raw->getError()->willReturn(UPLOAD_ERR_NO_FILE);

            $filtered = $this->prophesize(UploadedFileInterface::class);
            $filtered->getError()->willReturn(UPLOAD_ERR_NO_FILE);

            yield $type => [
                'raw'      => $type === 'multi'
                    ? [$raw->reveal()]
                    : $raw->reveal(),
                'filtered' => $raw->reveal(),
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
        $fooUploadErrOk = $this->prophesize(UploadedFileInterface::class);
        $fooUploadErrOk->getError()->willReturn(UPLOAD_ERR_OK);

        return [
            'single' => [
                'raw'      => $fooUploadErrOk->reveal(),
                'filtered' => $fooUploadErrOk->reveal(),
            ],
            'multi'  => [
                'raw'      => [
                    $fooUploadErrOk->reveal(),
                ],
                'filtered' => $fooUploadErrOk->reveal(),
            ],
        ];
    }

    /** @return UploadedFileInterface */
    protected function getDummyValue(bool $raw = true)
    {
        $upload = $this->prophesize(UploadedFileInterface::class);
        $upload->getError()->willReturn(UPLOAD_ERR_OK);
        return $upload->reveal();
    }
}
