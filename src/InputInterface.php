<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChainInterface;
use Laminas\Validator\ValidatorChainInterface;

interface InputInterface
{
    public function setAllowEmpty(bool $allowEmpty): static;

    public function setBreakOnFailure(bool $breakOnFailure): static;

    public function setErrorMessage(string|null $errorMessage): static;

    /** @param non-empty-string|int $name */
    public function setName(string|int $name): static;

    public function setRequired(bool $required): static;

    public function setValue(mixed $value): static;

    public function merge(InputInterface $input): static;

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
}
