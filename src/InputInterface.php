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

    public function setFilterChain(FilterChainInterface $filterChain): static;

    /**
     * @param array-key $name
     * @return $this
     */
    public function setName($name);

    public function setRequired(bool $required): static;

    public function setValidatorChain(ValidatorChainInterface $validatorChain): static;

    public function setValue(mixed $value): static;

    public function merge(InputInterface $input): static;

    public function allowEmpty(): bool;

    public function breakOnFailure(): bool;

    /**
     * @return string|null
     */
    public function getErrorMessage();

    public function getFilterChain(): FilterChainInterface;

    /**
     * @return string
     */
    public function getName();

    public function getRawValue(): mixed;

    public function isRequired(): bool;

    public function getValidatorChain(): ValidatorChainInterface;

    public function getValue(): mixed;

    public function isValid(): bool;

    /**
     * @return array<array-key, string>
     */
    public function getMessages();
}
