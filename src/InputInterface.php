<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Validator\ValidatorChain;

interface InputInterface
{
    public function setAllowEmpty(bool $allowEmpty): static;

    public function setBreakOnFailure(bool $breakOnFailure): static;

    public function setErrorMessage(?string $errorMessage): static;

    public function setFilterChain(FilterChain $filterChain): static;

    public function setName(string $name): static;

    public function setRequired(bool $required): static;

    public function setValidatorChain(ValidatorChain $validatorChain): static;

    public function setValue(mixed $value): static;

    public function merge(InputInterface $input): InputInterface;

    public function allowEmpty(): bool;

    public function breakOnFailure(): bool;

    public function getErrorMessage(): ?string;

    public function getFilterChain(): ?FilterChain;

    public function getName(): ?string;

    public function isRequired(): bool;

    public function getValidatorChain(): ?ValidatorChain;

    public function getValue(): mixed;
}
