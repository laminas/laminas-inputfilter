<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput;

use Laminas\InputFilter\FileInput\HttpServerFileInputHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

/**
 * @psalm-suppress DeprecatedMethod
 * @psalm-suppress MixedArgument
 */
#[CoversClass(HttpServerFileInputHandler::class)]
final class HttpServerFileInputHandlerTest extends TestCase
{
    #[DataProvider('isEmptyProvider')]
    public function testIsEmpty(array $value, bool $expected): void
    {
        self::assertEquals($expected, HttpServerFileInputHandler::isEmptyFile($value));
    }

    /**
     * @return array<string, array{
     *      array,
     *      bool
     *  }>
     */
    public static function isEmptyProvider(): array
    {
        return [
            'No HTTPServer file'            => [['tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE], true],
            'No HTTPServer multi file'      => [
                [
                    [
                        'tmp_name' => 'foo',
                        'error'    => UPLOAD_ERR_NO_FILE,
                    ],
                ],
                true,
            ],
            'Present HTTPServer file'       => [['tmp_name' => 'foo', 'error' => UPLOAD_ERR_OK], false],
            'Present HTTPServer multi file' => [
                [
                    [
                        'tmp_name' => 'foo',
                        'error'    => UPLOAD_ERR_OK,
                    ],
                    [
                        'tmp_name' => 'bar',
                        'error'    => UPLOAD_ERR_OK,
                    ],
                ],
                false,
            ],
        ];
    }
}
