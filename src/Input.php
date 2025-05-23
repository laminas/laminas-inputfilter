<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\ValidatorChain;

use function class_exists;

class Input implements
    InputInterface,
    EmptyContextInterface
{
    protected bool $allowEmpty = false;

    protected bool $continueIfEmpty = false;

    protected bool $breakOnFailure = false;

    protected ?string $errorMessage = null;

    protected ?FilterChain $filterChain = null;

    protected bool $notEmptyValidator = false;

    protected bool $required = true;

    protected ?ValidatorChain $validatorChain = null;

    protected mixed $value;

    /**
     * Flag for distinguish when $value contains the value previously set or the default one.
     */
    protected bool $hasValue = false;

    protected mixed $fallbackValue;

    protected bool $hasFallback = false;

    public function __construct(protected ?string $name = null)
    {
    }

    public function setAllowEmpty(bool $allowEmpty): static
    {
        $this->allowEmpty = $allowEmpty;
        return $this;
    }

    public function setBreakOnFailure(bool $breakOnFailure): static
    {
        $this->breakOnFailure = (bool) $breakOnFailure;
        return $this;
    }

    public function setContinueIfEmpty(bool $continueIfEmpty): static
    {
        $this->continueIfEmpty = (bool) $continueIfEmpty;
        return $this;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage ?? null;
        return $this;
    }

    public function setFilterChain(FilterChain $filterChain): static
    {
        $this->filterChain = $filterChain;
        return $this;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;
        return $this;
    }

    public function setValidatorChain(ValidatorChain $validatorChain): static
    {
        $this->validatorChain = $validatorChain;
        return $this;
    }

    public function setValue(mixed $value): static
    {
        $this->value    = $value;
        $this->hasValue = true;
        return $this;
    }

    /**
     * Reset input value to the default state.
     *
     * @see Input::setValue() For set a new value.
     * @see Input::hasValue() For to know if input value was set.
     *
     * @return $this
     */
    public function resetValue(): static
    {
        $this->value    = null;
        $this->hasValue = false;
        return $this;
    }

    public function setFallbackValue(mixed $value): static
    {
        $this->fallbackValue = $value;
        $this->hasFallback   = true;
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

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getFilterChain(): ?FilterChain
    {
        return $this->filterChain;
    }

    public function getName(): ?string
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

    public function getValidatorChain(): ?ValidatorChain
    {
        return $this->validatorChain;
    }

    public function getValue(): mixed
    {
        $filter = $this->getFilterChain();
        return $filter->filter($this->value);
    }

    /**
     * Flag for inform if input value was set.
     *
     * This flag used for distinguish when {@link Input::getValue()}
     * will return the value previously set or the default.
     *
     * @see Input::setValue() For set a new value.
     * @see Input::resetValue() For reset the input value to the default state.
     * @see Input::getValue() For retrieve the input value.
     */
    public function hasValue(): bool
    {
        return $this->hasValue;
    }

    public function getFallbackValue(): mixed
    {
        return $this->fallbackValue;
    }

    public function hasFallback(): bool
    {
        return $this->hasFallback;
    }

    public function clearFallbackValue(): void
    {
        $this->hasFallback   = false;
        $this->fallbackValue = null;
    }

    public function merge(InputInterface $input): static
    {
        $this->setBreakOnFailure($input->breakOnFailure());
        if ($input instanceof Input) {
            $this->setContinueIfEmpty($input->continueIfEmpty());
        }
        $this->setErrorMessage($input->getErrorMessage());
        $this->setName($input->getName());
        $this->setRequired($input->isRequired());
        $this->setAllowEmpty($input->allowEmpty());
        if (! $input instanceof Input || $input->hasValue()) {
            $this->setValue($input->getRawValue());
        }

        $filterChain = $input->getFilterChain();
        $this->getFilterChain()->merge($filterChain);

        $validatorChain = $input->getValidatorChain();
        $this->getValidatorChain()->merge($validatorChain);
        return $this;
    }

    /**
     * @param mixed $context Extra "context" to provide the validator
     */
    public function isValid(mixed $context = null): bool
    {
        $value           = $this->getValue();
        $hasValue        = $this->hasValue();
        $empty           = $value === null || $value === '' || $value === [];
        $required        = $this->isRequired();
        $allowEmpty      = $this->allowEmpty();
        $continueIfEmpty = $this->continueIfEmpty();

        if (! $hasValue && $this->hasFallback()) {
            $this->setValue($this->getFallbackValue());
            return true;
        }

        if (! $hasValue && ! $required) {
            return true;
        }

        if (! $hasValue) { // required, but no value
            if ($this->errorMessage === null) {
                $this->errorMessage = $this->prepareRequiredValidationFailureMessage();
            }
            return false;
        }

        if ($empty && ! $required && ! $continueIfEmpty) {
            return true;
        }

        if ($empty && $allowEmpty && ! $continueIfEmpty) {
            return true;
        }

        // At this point, we need to run validators.
        // If we do not allow empty and the "continue if empty" flag are
        // BOTH false, we inject the "not empty" validator into the chain,
        // which adds that logic into the validation routine.
        if (! $allowEmpty && ! $continueIfEmpty) {
            $this->injectNotEmptyValidator();
        }

        $validator = $this->getValidatorChain();
        $result    = $validator->isValid($value, $context);
        if (! $result && $this->hasFallback()) {
            $this->setValue($this->getFallbackValue());
            $result = true;
        }

        return $result;
    }

    /**
     * @return array<array-key, string>
     */
    public function getMessages(): array
    {
        if (null !== $this->errorMessage) {
            return (array) $this->errorMessage;
        }

        if ($this->hasFallback()) {
            return [];
        }

        $validator = $this->getValidatorChain();
        return $validator?->getMessages() ?: [];
    }

    protected function injectNotEmptyValidator(): void
    {
        if ((! $this->isRequired() && $this->allowEmpty()) || $this->notEmptyValidator) {
            return;
        }
        $chain = $this->getValidatorChain();

        // Check if NotEmpty validator is already in chain
        $validators = $chain->getValidators();
        foreach ($validators as $validator) {
            if ($validator['instance'] instanceof NotEmpty) {
                $this->notEmptyValidator = true;
                return;
            }
        }

        $this->notEmptyValidator = true;

        if (class_exists(AbstractPluginManager::class)) {
            $chain->prependByName(NotEmpty::class, [], true);

            return;
        }

        $chain->prependValidator(new NotEmpty(), true);
    }

    /**
     * Create and return the validation failure message for required input.
     */
    protected function prepareRequiredValidationFailureMessage(): ?string
    {
        $chain    = $this->getValidatorChain();
        $notEmpty = $chain->plugin(NotEmpty::class);

        foreach ($chain->getValidators() as $validator) {
            if ($validator['instance'] instanceof NotEmpty) {
                $notEmpty = $validator['instance'];
                break;
            }
        }

        return $notEmpty->getMessages()[NotEmpty::IS_EMPTY] ?? null;
    }
}
