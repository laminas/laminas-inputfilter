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

    /**
     * @deprecated Since 3.0. Setting the value to be validated is deprecated as part of the old stateful API.
     *             Use the {@link validate()} method instead.
     */
    public function setValue(mixed $value): static;

    public function allowEmpty(): bool;

    public function breakOnFailure(): bool;

    /**
     * @deprecated Since 3.0. Error messages are included in the result of {@link validate()} and should not be
     *             retrieved from the input at runtime.
     */
    public function getErrorMessage(): string|null;

    public function getFilterChain(): FilterChainInterface;

    /** @return non-empty-string|int */
    public function getName(): int|string;

    /**
     * @deprecated Since 3.0. The raw, unfiltered value is included in the result of {@link validate()} and is not
     *             present when using the new validation API. This method continues to work as it did previously
     *             when using the old API.
     */
    public function getRawValue(): mixed;

    public function isRequired(): bool;

    public function getValidatorChain(): ValidatorChainInterface;

    /**
     * @deprecated Since 3.0. The filtered value is included in the result of {@link validate()} and is not
     *             present when using the new validation API. This method continues to work as it did previously
     *             when using the old API.
     */
    public function getValue(): mixed;

    /**
     * @deprecated Since 3.0. Please migrate to the new validation method {@link validate()} that returns a result
     *             object instead of a boolean, and does not mutate internal state.
     *
     * @see validate()
     *
     * @param array<array-key, mixed>|null $context
     */
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

    /**
     * @deprecated Since 3.0. Error messages are included in the result of {@link validate()} and should not be
     *             retrieved from the input at runtime.
     */
    public function getMessages(): ErrorMessages;

    public function continueIfEmpty(): bool;

    public function getFallbackValue(): mixed;

    public function hasFallback(): bool;
}
