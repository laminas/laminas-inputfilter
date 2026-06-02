<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput;

use Laminas\Filter\FilterChainInterface;
use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\InputInterface;
use Laminas\Validator\File\UploadFile;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorChainInterface;
use LaminasTest\InputFilter\TestAsset\UploadedFileInterfaceStub;
use LaminasTest\InputFilter\TestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

use function count;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final class FileInputValidateTest extends TestCase
{
    /** @param non-empty-string $name */
    private function createFileInput(
        string $name = 'foo',
        ?FilterChainInterface $filterChain = null,
        ?ValidatorChainInterface $validatorChain = null,
    ): FileInput {
        $filterChain    ??= TestHelper::createFilterChain();
        $validatorChain ??= TestHelper::createValidatorChain();

        $input = new FileInput($filterChain, $validatorChain, $name);
        // Upload validator does not work in CLI test environment, disable
        $input->setAutoPrependUploadValidator(false);

        return $input;
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

    #[DataProvider('validSingleValueProvider')]
    public function testRetrievingValueFiltersTheValueOnlyAfterValidating(
        mixed $raw,
        mixed $filtered,
    ): void {
        $input = $this->createFileInput(
            'foo',
            TestHelper::createFilterChainFixture($raw, $filtered),
        );

        $result = $input->validate($raw, []);

        self::assertEquals($raw, $result->rawValue());
        self::assertTrue(
            $result->valid(),
            sprintf(
                'Expected a valid result. Messages: %s',
                json_encode($result->getMessages()->toArray(), JSON_THROW_ON_ERROR),
            ),
        );
        self::assertEquals($filtered, $result->value());
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

    #[DataProvider('validMultiValueProvider')]
    public function testCanFilterArrayOfMultiFileData(array $raw, array $filtered): void
    {
        $map = [];
        for ($i = 0; $i < count($filtered); $i += 1) {
            $map[] = [$raw[$i], $filtered[$i]];
        }

        $input  = $this->createFileInput('foo', TestHelper::createFilterChainFixtureFromMap($map));
        $result = $input->validate($raw, []);

        self::assertEquals($raw, $result->rawValue());
        self::assertTrue(
            $result->valid(),
            'valid() value not match. Detail . ' . json_encode($result->getMessages(), JSON_THROW_ON_ERROR),
        );
        self::assertEquals(
            $filtered,
            $result->value(),
        );
    }

    #[DataProvider('validSingleValueProvider')]
    public function testCanRetrieveRawValueFromValidationResult(mixed $raw, mixed $filtered): void
    {
        $input  = $this->createFileInput(
            'foo',
            TestHelper::createFilterChainFixture($raw, $filtered),
        );
        $result = $input->validate($raw, []);

        self::assertEquals($raw, $result->rawValue());
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

    #[DataProvider('invalidSingleValueProvider')]
    public function testValidationOperatesBeforeFiltering(mixed $raw, mixed $filtered): void
    {
        $input = $this->createFileInput(
            'foo',
            TestHelper::createFilterChainFixture($raw, $filtered),
            TestHelper::createValidatorChain($raw, false),
        );

        $result = $input->validate($raw, []);

        self::assertFalse($result->valid());
        self::assertEquals($raw, $result->rawValue());
        self::assertEquals($raw, $result->value());
    }

    public function testUploadValidatorIsAddedWhenIsValidateIsCalled(): void
    {
        $input = $this->createFileInput();
        $input->setAutoPrependUploadValidator(true);
        self::assertTrue($input->getAutoPrependUploadValidator());
        self::assertTrue($input->isRequired());

        $validatorChain = $input->getValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $validatorChain);
        self::assertCount(0, $validatorChain->getValidators());

        $result = $input->validate(null, []);

        self::assertFalse($result->valid());
        $validators = $validatorChain->getValidators();
        self::assertCount(1, $validators);
        self::assertInstanceOf(UploadFile::class, $validators[0]['instance']);
    }

    public function testUploadValidatorIsNotAddedWhenNotDesired(): void
    {
        $input = $this->createFileInput();
        self::assertFalse($input->getAutoPrependUploadValidator());
        self::assertTrue($input->isRequired());
        $result         = $input->validate(['tmp_name' => 'bar'], []);
        $validatorChain = $input->getValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $validatorChain);
        self::assertCount(0, $validatorChain->getValidators());
        self::assertTrue($result->valid());
        self::assertCount(0, $validatorChain->getValidators());
    }

    /** @return list<array{0: mixed}> */
    public static function emptyValueProvider(): array
    {
        return [
            [''],
            [[]],
            [null],
        ];
    }

    #[DataProvider('emptyValueProvider')]
    public function testEmptyValidationFailureMessageIsPresentWhenEmptyValuesPassValidation(mixed $empty): void
    {
        $input = $this->createFileInput();
        $input->setRequired(true);
        $input->setAllowEmpty(false);
        $input->setContinueIfEmpty(true);

        $result = $input->validate($empty, []);
        self::assertFalse($result->valid());
        self::assertCount(1, $result->getMessages());
        self::assertArrayHasKey(InputInterface::EMPTY_FAILURE_VALIDATION_KEY, $result->getMessages()->toArray());
        self::assertSame(
            'The value for "foo" was empty, but its configuration prohibits an empty value. '
            . 'Enable the auto-prepend of a "UploadFile" validator to this input’s chain, '
            . 'or configure one manually in order to customise '
            . 'this validation failure message',
            $result->getMessages()[InputInterface::EMPTY_FAILURE_VALIDATION_KEY],
        );
    }

    public function testEmptyValidationFailureMessageIsNotPresentWhenUploadValidatorIsPresent(): void
    {
        $input = $this->createFileInput();
        $input->setRequired(true);
        $input->setAllowEmpty(false);
        $input->setContinueIfEmpty(true);
        $input->setAutoPrependUploadValidator(true);

        $result = $input->validate('', ['foo' => '']);
        self::assertFalse($result->valid());
        $messages = $result->getMessages()->toArray();
        self::assertCount(1, $messages);
        self::assertArrayHasKey(UploadFile::NO_FILE, $messages);
        self::assertArrayNotHasKey(InputInterface::EMPTY_FAILURE_VALIDATION_KEY, $messages);
    }

    /** @return list<array{0: mixed, 1: bool, 2: bool, 3: bool}> */
    public static function emptinessMatrix(): array
    {
        return [
            ['', false, true, false],
            [[], false, true, false],
            [null, false, true, false],
            ['', true, true, false],
            [[], true, true, false],
            [null, true, true, false],
            ['', false, false, false],
            [[], false, false, false],
            [null, false, false, false],
        ];
    }

    #[DataProvider('emptinessMatrix')]
    public function testPassWhenEmptinessOptionsPermit(
        mixed $value,
        bool $required,
        bool $allowEmpty,
        bool $continue,
    ): void {
        $input = $this->createFileInput();
        $input->setRequired($required);
        $input->setAllowEmpty($allowEmpty);
        $input->setContinueIfEmpty($continue);

        $result = $input->validate($value, ['foo' => '']);
        self::assertTrue($result->valid());
    }
}
