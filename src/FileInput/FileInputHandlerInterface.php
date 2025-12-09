<?php

declare(strict_types=1);

namespace Laminas\InputFilter\FileInput;

use Laminas\Filter\FilterChainInterface;
use Laminas\Validator\ValidatorChainInterface;

/**
 * FileInputInterface defines expected methods for validating and filtering uploaded files.
 *
 * FileInput will consume instances of this interface when filtering files,
 * allowing it to switch between SAPI uploads and PSR-7 UploadedFileInterface
 * instances.
 *
 * @psalm-internal Laminas\InputFilter
 * @psalm-internal LaminasTest\InputFilter
 */
interface FileInputHandlerInterface
{
    /** Checks if the raw input value is an empty file input eg: no file was uploaded */
    public static function isEmptyFile(mixed $rawValue): bool;

    public function filterValue(mixed $value, bool $isValid, FilterChainInterface $filterChain): mixed;

    /** @param array<string, mixed> $context Extra "context" to provide the validator */
    public function isValid(mixed $rawValue, ValidatorChainInterface $validatorChain, ?array $context = null): bool;
}
