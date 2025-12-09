<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Exception;
use Laminas\Filter\FilterChain;
use Laminas\InputFilter\InputInterface;
use Laminas\Validator\ValidatorChain;

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

    /** @inheritDoc */
    public function setAllowEmpty($allowEmpty): static
    {
        return $this;
    }

    /** @inheritDoc */
    public function setBreakOnFailure($breakOnFailure): static
    {
        return $this;
    }

    /** @inheritDoc */
    public function setErrorMessage($errorMessage): static
    {
        return $this;
    }

    /** @inheritDoc */
    public function setFilterChain(FilterChain $filterChain): never
    {
        throw new Exception('Not implemented');
    }

    /** @inheritDoc */
    public function setName($name): never
    {
        throw new Exception('Not implemented');
    }

    /** @inheritDoc */
    public function setRequired($required): never
    {
        throw new Exception('Not implemented');
    }

    /** @inheritDoc */
    public function setValidatorChain(ValidatorChain $validatorChain): never
    {
        throw new Exception('Not implemented');
    }

    /** @inheritDoc */
    public function setValue($value): static
    {
        return $this;
    }

    /** @inheritDoc */
    public function merge(InputInterface $input): never
    {
        throw new Exception('Not implemented');
    }

    /** @inheritDoc */
    public function allowEmpty(): never
    {
        throw new Exception('Not implemented');
    }

    /** @inheritDoc */
    public function breakOnFailure(): bool
    {
        return $this->breakOnFailure;
    }

    /** @inheritDoc */
    public function getErrorMessage(): never
    {
        throw new Exception('Not implemented');
    }

    /** @inheritDoc */
    public function getFilterChain(): never
    {
        throw new Exception('Not implemented');
    }

    /** @inheritDoc */
    public function getName(): string
    {
        return $this->name;
    }

    /** @inheritDoc */
    public function getRawValue(): mixed
    {
        return $this->getRawValue;
    }

    /** @inheritDoc */
    public function isRequired(): bool
    {
        assertNotNull($this->isRequired, 'isRequired was not expected to be called');

        return $this->isRequired;
    }

    /** @inheritDoc */
    public function getValidatorChain(): never
    {
        throw new Exception('Not implemented');
    }

    /** @inheritDoc */
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
