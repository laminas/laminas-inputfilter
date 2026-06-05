<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Exception;
use Laminas\Filter\FilterChainInterface;
use Laminas\InputFilter\ErrorMessages;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputInterface;
use Laminas\InputFilter\InputValidationResult;
use Laminas\Validator\ValidatorChainInterface;
use NoDiscard;

/** @psalm-import-type InputSpecification from InputFilterInterface */
final class InputInterfaceImplementation implements InputInterface
{
    private readonly bool $allowEmpty;
    private readonly bool $continueIfEmpty;
    private readonly bool $breakOnFailure;
    private readonly bool $required;
    private mixed $value;
    private readonly mixed $fallbackValue;
    private readonly bool $hasFallback;
    private readonly string|null $errorMessage;

    /**
     * @param non-empty-string|int $name
     * @param InputSpecification $options
     */
    public function __construct(
        private readonly FilterChainInterface $filterChain,
        private readonly ValidatorChainInterface $validatorChain,
        private readonly string|int $name,
        array $options = [],
    ) {
        $this->allowEmpty      = $options['allow_empty'] ?? false;
        $this->required        = $options['required'] ?? $this->allowEmpty !== true;
        $this->continueIfEmpty = $options['continue_if_empty'] ?? false;
        $this->breakOnFailure  = $options['break_on_failure'] ?? false;
        $this->errorMessage    = $options['error_message'] ?? null;
        $this->fallbackValue   = $options['fallback_value'] ?? null;
        $this->hasFallback     = $this->fallbackValue !== null;
    }

    public function setValue(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function allowEmpty(): bool
    {
        return $this->allowEmpty;
    }

    public function breakOnFailure(): bool
    {
        return $this->breakOnFailure;
    }

    public function continueIfEmpty(): bool
    {
        return $this->continueIfEmpty;
    }

    public function getErrorMessage(): string|null
    {
        return $this->errorMessage;
    }

    public function getFilterChain(): FilterChainInterface
    {
        return $this->filterChain;
    }

    public function getName(): int|string
    {
        return $this->name;
    }

    public function getRawValue(): mixed
    {
        return $this->value;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getValidatorChain(): ValidatorChainInterface
    {
        return $this->validatorChain;
    }

    public function getValue(): mixed
    {
        return $this->filterChain->filter($this->value);
    }

    public function isValid(?array $context = null): bool
    {
        return $this->validatorChain->isValid($this->getValue(), $context);
    }

    public function getMessages(): ErrorMessages
    {
        return new ErrorMessages($this->validatorChain->getMessages());
    }

    public function getFallbackValue(): mixed
    {
        return $this->fallbackValue;
    }

    public function hasFallback(): bool
    {
        return $this->hasFallback;
    }

    #[NoDiscard]
    public function validate(mixed $value, array $context): InputValidationResult
    {
        throw new Exception('Not implemented');
    }
}
