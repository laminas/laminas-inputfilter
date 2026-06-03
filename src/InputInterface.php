<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChainInterface;
use Laminas\Validator\ValidatorChainInterface;
use NoDiscard;

interface InputInterface
{
    /** @internal */
    public const EMPTY_FAILURE_VALIDATION_KEY = '__inputEmptyValueFailure';

    public function setValue(mixed $value): static;

    public function allowEmpty(): bool;

    public function breakOnFailure(): bool;

    public function getErrorMessage(): string|null;

    public function getFilterChain(): FilterChainInterface;

    /** @return non-empty-string|int */
    public function getName(): int|string;

    public function getRawValue(): mixed;

    public function isRequired(): bool;

    public function getValidatorChain(): ValidatorChainInterface;

    public function getValue(): mixed;

    /** @param array<array-key, mixed>|null $context */
    public function isValid(array|null $context = null): bool;

    /**
     * Validate a value for this input
     *
     * This method performs stateless validation, returning a result object that exposes whether validation was
     * successful, any error messages, the filtered, and un-filtered values.
     *
     * This method does not modify the internal state of the input, therefore it is not necessary to `setValue()`, and
     * subsequent calls to `getValue()`, `isValid()`, `getMessages()` and others will not yield the expected results.
     *
     * Before migrating to this method, please familiarise yourself with the migration guide for version 3.x
     *
     * @param array<array-key, mixed> $context
     */
    #[NoDiscard]
    public function validate(mixed $value, array $context): InputValidationResult;

    public function getMessages(): ErrorMessages;

    public function continueIfEmpty(): bool;

    public function getFallbackValue(): mixed;

    public function hasFallback(): bool;
}
