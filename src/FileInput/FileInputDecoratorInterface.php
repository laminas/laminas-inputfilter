<?php

declare(strict_types=1);

namespace Laminas\InputFilter\FileInput;

/**
 * FileInputInterface defines expected methods for filtering uploaded files.
 *
 * FileInput will consume instances of this interface when filtering files,
 * allowing it to switch between SAPI uploads and PSR-7 UploadedFileInterface
 * instances.
 */
interface FileInputDecoratorInterface
{
    /**
     * Checks if the raw input value is an empty file input eg: no file was uploaded
     */
    public static function isEmptyFileDecorator(mixed $rawValue): bool;

    public function getValue(): mixed;

    /**
     * @param mixed|null $context Extra "context" to provide the validator
     */
    public function isValid(mixed $context = null): bool;
}
