<?php

declare(strict_types=1);

namespace Laminas\InputFilter\FileInput;

use Laminas\Filter\FilterChainInterface;
use Laminas\Validator\ValidatorChainInterface;

use function count;
use function is_array;

use const UPLOAD_ERR_NO_FILE;

/**
 * Handler for validating and filtering standard SAPI file uploads.
 *
 * It differs from Input in a few ways:
 *
 * 1. It expects the raw value to be in the $_FILES array format.
 *
 * 2. The validators are run **before** the filters (the opposite behavior of Input).
 *    This is so is_uploaded_file() validation can be run prior to any filters that
 *    may rename/move/modify the file.
 *
 * 3. Instead of adding a NotEmpty validator, it will (by default) automatically add
 *    a Laminas\Validator\File\Upload validator.
 *
 * @psalm-internal Laminas\InputFilter
 * @psalm-internal LaminasTest\InputFilter
 */
final class HttpServerFileInputHandler implements FileInputHandlerInterface
{
    /** Checks if the raw input value is an empty file input eg: no file was uploaded */
    public static function isEmptyFile(mixed $rawValue): bool
    {
        if (! is_array($rawValue)) {
            return true;
        }

        if (isset($rawValue['error']) && $rawValue['error'] === UPLOAD_ERR_NO_FILE) {
            return true;
        }

        if (count($rawValue) === 1 && isset($rawValue[0])) {
            return self::isEmptyFile($rawValue[0]);
        }

        return false;
    }

    public function filterValue(mixed $value, bool $isValid, FilterChainInterface $filterChain): mixed
    {
        if (! $isValid || ! is_array($value)) {
            return $value;
        }

        // Run filters ~after~ validation, so that is_uploaded_file()
        // validation is not affected by filters.
        if (isset($value['tmp_name'])) {
            // Single file input
            return $filterChain->filter($value);
        }

        // Multi file input (multiple attribute set)
        $newValue = [];
        foreach ($value as $fileData) {
            if (is_array($fileData) && isset($fileData['tmp_name'])) {
                $newValue[] = $filterChain->filter($fileData);
            }
        }

        return $newValue;
    }

    /**
     * @param array<string, mixed>|null $context Extra "context" to provide the validator
     */
    public function isValid(mixed $rawValue, ValidatorChainInterface $validatorChain, ?array $context = null): bool
    {
        if (! is_array($rawValue)) {
            // This can happen in an AJAX POST, where the input comes across as a string
            $rawValue = [
                'tmp_name' => $rawValue,
                'name'     => $rawValue,
                'size'     => 0,
                'type'     => '',
                'error'    => UPLOAD_ERR_NO_FILE,
            ];
        } elseif (! isset($rawValue['tmp_name']) && ! isset($rawValue[0]['tmp_name'])) {
            // This can happen when sent not file and just array
            $rawValue = [
                'tmp_name' => '',
                'name'     => '',
                'size'     => 0,
                'type'     => '',
                'error'    => UPLOAD_ERR_NO_FILE,
            ];
        }

        if (isset($rawValue['tmp_name'])) {
            // Single file input
            return $validatorChain->isValid($rawValue, $context);
        }

        if (isset($rawValue[0]['tmp_name'])) {
            // Multi file input (multiple attribute set)
            foreach ($rawValue as $value) {
                if (! $validatorChain->isValid($value, $context)) {
                    return false; // Do not continue processing files if validation fails
                }
            }

            return true; // We return early from the loop if validation fails
        }

        return false;
    }
}
