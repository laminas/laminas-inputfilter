<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChainInterface;
use Laminas\Validator\ValidatorChainInterface;

interface InputInterface
{
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

    public function getMessages(): ErrorMessages;

    public function continueIfEmpty(): bool;

    public function getFallbackValue(): mixed;

    public function hasFallback(): bool;
}
