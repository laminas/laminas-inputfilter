<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput;

use Laminas\InputFilter\FileInput\PsrFileInputHandler;
use LaminasTest\InputFilter\TestAsset\UploadedFileInterfaceStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

use const UPLOAD_ERR_NO_FILE;

/**
 * @psalm-suppress DeprecatedMethod
 */
#[CoversClass(PsrFileInputHandler::class)]
final class PsrFileInputHandlerTest extends TestCase
{
    #[DataProvider('isEmptyProvider')]
    public function testIsEmpty(array|UploadedFileInterface $value, bool $expected): void
    {
        self::assertEquals($expected, PsrFileInputHandler::isEmptyFile($value));
    }

    /**
     * @return array<string, array{
     *      array|UploadedFileInterface,
     *      bool
     *  }>
     */
    public static function isEmptyProvider(): array
    {
        return [
            'No PSR7 file'            => [new UploadedFileInterfaceStub(UPLOAD_ERR_NO_FILE), true],
            'No PSR7 multi file'      => [
                [
                    new UploadedFileInterfaceStub(UPLOAD_ERR_NO_FILE),
                    new UploadedFileInterfaceStub(UPLOAD_ERR_NO_FILE),
                ],
                true,
            ],
            'Present PSR7 file'       => [new UploadedFileInterfaceStub(), false],
            'Present PSR7 multi file' => [
                [
                    new UploadedFileInterfaceStub(),
                    new UploadedFileInterfaceStub(),
                ],
                false,
            ],
        ];
    }
}
