<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChainInterface;
use Laminas\Validator\ValidatorChainInterface;

/** @psalm-import-type InputErrorMessages from InputFilterInterface */
interface InputInterface
{
    public function setAllowEmpty(bool $allowEmpty): static;

    public function setBreakOnFailure(bool $breakOnFailure): static;

    public function setErrorMessage(string|null $errorMessage): static;

    public function setFilterChain(FilterChainInterface $filterChain): static;

    public function setName(string|int $name): static;

    public function setRequired(bool $required): static;

    public function setValidatorChain(ValidatorChainInterface $validatorChain): static;

    public function setValue(mixed $value): static;

    public function merge(InputInterface $input): static;

    public function allowEmpty(): bool;

    public function breakOnFailure(): bool;

    public function getErrorMessage(): string|null;

    public function getFilterChain(): FilterChainInterface;

    public function getName(): int|string|null;

    public function getRawValue(): mixed;

    public function isRequired(): bool;

    public function getValidatorChain(): ValidatorChainInterface;

    public function getValue(): mixed;

    /** @param array<array-key, mixed>|null $context */
    public function isValid(array|null $context = null): bool;

    /** @return InputErrorMessages */
    public function getMessages(): array;
}
