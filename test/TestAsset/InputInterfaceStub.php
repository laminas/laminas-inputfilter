<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Exception;
use Laminas\Filter\FilterChainInterface;
use Laminas\InputFilter\InputInterface;
use Laminas\Validator\ValidatorChainInterface;

use function func_get_arg;
use function func_num_args;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNotNull;

final readonly class InputInterfaceStub implements InputInterface
{
    /** @param array<string, string> $getMessages */
    public function __construct(
        private string $name,
        private bool|null $isRequired,
        private bool|null $isValid = null,
        private array|string|null $context = null,
        private mixed $getRawValue = null,
        private mixed $getValue = null,
        private array $getMessages = [],
        private bool $breakOnFailure = false
    ) {
    }

    public function setAllowEmpty(bool $allowEmpty): static
    {
        return $this;
    }

    public function setBreakOnFailure(bool $breakOnFailure): static
    {
        return $this;
    }

    public function setErrorMessage(string|null $errorMessage): static
    {
        return $this;
    }

    public function setFilterChain(FilterChainInterface $filterChain): never
    {
        throw new Exception('Not implemented');
    }

    public function setName(string|int $name): never
    {
        throw new Exception('Not implemented');
    }

    public function setRequired(bool $required): never
    {
        throw new Exception('Not implemented');
    }

    public function setValidatorChain(ValidatorChainInterface $validatorChain): never
    {
        throw new Exception('Not implemented');
    }

    public function setValue(mixed $value): static
    {
        return $this;
    }

    public function merge(InputInterface $input): never
    {
        throw new Exception('Not implemented');
    }

    public function allowEmpty(): never
    {
        throw new Exception('Not implemented');
    }

    public function breakOnFailure(): bool
    {
        return $this->breakOnFailure;
    }

    public function getErrorMessage(): never
    {
        throw new Exception('Not implemented');
    }

    public function getFilterChain(): never
    {
        throw new Exception('Not implemented');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRawValue(): mixed
    {
        return $this->getRawValue;
    }

    public function isRequired(): bool
    {
        assertNotNull($this->isRequired, 'isRequired was not expected to be called');

        return $this->isRequired;
    }

    public function getValidatorChain(): never
    {
        throw new Exception('Not implemented');
    }

    public function getValue(): mixed
    {
        return $this->getValue;
    }

    /** @inheritDoc */
    public function isValid(): bool
    {
        assertNotNull($this->isValid, 'isValid was not expected to be called');

        if ($this->context !== null && func_num_args() > 0) {
            assertEquals($this->context, func_get_arg(0), 'The given context does not match the expected context');
        }

        return $this->isValid;
    }

    /** @return array<string, string> */
    public function getMessages(): array
    {
        return $this->getMessages;
    }
}
