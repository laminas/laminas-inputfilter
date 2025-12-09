<?php

declare(strict_types=1);

namespace Laminas\InputFilter\FileInput;

use Laminas\Filter\FilterChainInterface;
use Laminas\Validator\ValidatorChainInterface;
use Psr\Http\Message\UploadedFileInterface;

use function is_array;

use const UPLOAD_ERR_NO_FILE;

/**
 * PsrFileInput is a special Input type for handling uploaded files through  PSR-7 middlware.
 *
 * It differs from Input in a few ways:
 *
 * 1. It expects the raw value to be an instance of UploadedFileInterface.
 *
 * 2. The validators are run **before** the filters (the opposite behavior of Input).
 *    This is so validation can be run prior to any filters that may
 *    rename/move/modify the file.
 *
 * 3. Instead of adding a NotEmpty validator, it will (by default) automatically add
 *    a Laminas\Validator\File\Upload validator.
 *
 * @psalm-internal Laminas\InputFilter
 * @psalm-internal LaminasTest\InputFilter
 */
final class PsrFileInputHandler implements FileInputHandlerInterface
{
    /**
     * Checks if the raw input value is an empty file input eg: no file was uploaded
     *
     * @param UploadedFileInterface|array $rawValue
     */
    public static function isEmptyFile(mixed $rawValue): bool
    {
        if (is_array($rawValue)) {
            return self::isEmptyFile($rawValue[0]);
        }

        return $rawValue->getError() === UPLOAD_ERR_NO_FILE;
    }

    public function filterValue(mixed $value, bool $isValid, FilterChainInterface $filterChain): mixed
    {
        // Run filters ~after~ validation, so that is_uploaded_file()
        // validation is not affected by filters.
        if (! $isValid) {
            return $value;
        }

        if (is_array($value)) {
            // Multi file input (multiple attribute set)
            $newValue = [];
            foreach ($value as $fileData) {
                $newValue[] = $filterChain->filter($fileData);
            }
            return $newValue;
        }

        // Single file input
        return $filterChain->filter($value);
    }

    /**
     * @param array<string, mixed>|null $context Extra "context" to provide the validator
     */
    public function isValid(mixed $rawValue, ValidatorChainInterface $validatorChain, ?array $context = null): bool
    {
        if (is_array($rawValue)) {
            // Multi file input (multiple attribute set)
            foreach ($rawValue as $value) {
                if (! $validatorChain->isValid($value, $context)) {
                    return false; // Do not continue processing files if validation fails
                }
            }
            return true; // We return early from the loop if validation fails
        }

        // Single file input
        return $validatorChain->isValid($rawValue, $context);
    }
}
