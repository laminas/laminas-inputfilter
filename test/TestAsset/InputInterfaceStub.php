<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Exception;
use Laminas\InputFilter\ErrorMessages;
use Laminas\InputFilter\InputInterface;

use function func_get_arg;
use function func_num_args;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNotNull;

final readonly class InputInterfaceStub implements InputInterface
{
    /**
     * @param non-empty-string|int $name
     * @param array<string, string> $getMessages
     */
    public function __construct(
        private string|int $name,
        private bool|null $isRequired,
        private bool|null $isValid = null,
        private array|string|null $context = null,
        private mixed $getRawValue = null,
        private mixed $getValue = null,
        private array $getMessages = [],
        private bool $breakOnFailure = false,
        private bool $continueIfEmpty = false,
    ) {
    }

    public function setValue(mixed $value): static
    {
        return $this;
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

    public function getName(): string|int
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
    public function isValid(array|null $context = null): bool
    {
        assertNotNull($this->isValid, 'isValid was not expected to be called');

        if ($this->context !== null && func_num_args() > 0) {
            assertEquals($this->context, func_get_arg(0), 'The given context does not match the expected context');
        }

        return $this->isValid;
    }

    public function getMessages(): ErrorMessages
    {
        return new ErrorMessages($this->getMessages);
    }

    public function continueIfEmpty(): bool
    {
        return $this->continueIfEmpty;
    }

    public function getFallbackValue(): mixed
    {
        return null;
    }

    public function hasFallback(): bool
    {
        return false;
    }
}
