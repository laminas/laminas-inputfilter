<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterChainInterface;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorChainInterface;

use function assert;
use function class_exists;
use function is_array;
use function reset;

class Input implements
    InputInterface,
    EmptyContextInterface
{
    protected string|int|null $name = null;
    protected bool $allowEmpty      = false;
    protected bool $continueIfEmpty = false;
    protected bool $breakOnFailure  = false;
    /**
     * @todo ArrayInput needs refactoring so that this type cannot be an array
     * @var string|array<string, string>|null
     */
    protected string|array|null $errorMessage = null;
    protected bool $notEmptyValidator         = false;
    protected bool $required                  = true;
    protected mixed $value                    = null; // phpcs:ignore
    /**
     * Flag to distinguish when $value contains the value previously set or the default one.
     */
    protected bool $hasValue = false;
    protected mixed $fallbackValue;
    protected bool $hasFallback = false;

    public function __construct(
        protected FilterChainInterface $filterChain,
        protected ValidatorChainInterface $validatorChain,
        string|int|null $name = null
    ) {
        if ($name !== null) {
            $this->setName($name);
        }
    }

    public function setAllowEmpty(bool $allowEmpty): static
    {
        $this->allowEmpty = $allowEmpty;
        return $this;
    }

    public function setBreakOnFailure(bool $breakOnFailure): static
    {
        $this->breakOnFailure = $breakOnFailure;
        return $this;
    }

    public function setContinueIfEmpty(bool $continueIfEmpty): static
    {
        $this->continueIfEmpty = $continueIfEmpty;
        return $this;
    }

    public function setErrorMessage(string|null $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function setFilterChain(FilterChainInterface $filterChain): static
    {
        $this->filterChain = $filterChain;
        return $this;
    }

    public function setName(string|int $name): static
    {
        if ($name === '') {
            throw new InvalidArgumentException('Input names cannot be an empty string');
        }

        $this->name = $name;
        return $this;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;
        return $this;
    }

    public function setValidatorChain(ValidatorChainInterface $validatorChain): static
    {
        $this->validatorChain = $validatorChain;
        return $this;
    }

    /**
     * Set the input value.
     *
     * If you want to remove/unset the current value use {@link Input::resetValue()}.
     *
     * @see Input::getValue() For retrieve the input value.
     * @see Input::hasValue() For to know if input value was set.
     * @see Input::resetValue() For reset the input value to the default state.
     */
    public function setValue(mixed $value): static
    {
        $this->value    = $value;
        $this->hasValue = true;
        return $this;
    }

    /**
     * Reset input value to the default state.
     *
     * @see Input::hasValue() For to know if input value was set.
     * @see Input::setValue() For set a new value.
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

    /**
     * @todo Once ArrayInput is refactored, remove the array checks here
     */
    public function getErrorMessage(): string|null
    {
        $errorMessage = is_array($this->errorMessage)
            ? reset($this->errorMessage)
            : $this->errorMessage;

        return $errorMessage === false ? null : $errorMessage;
    }

    public function getFilterChain(): FilterChainInterface
    {
        return $this->filterChain;
    }

    public function getName(): int|string|null
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
        $filter = $this->getFilterChain();
        return $filter->filter($this->value);
    }

    /**
     * Flag for inform if input value was set.
     *
     * This flag used for distinguish when {@link Input::getValue()}
     * will return the value previously set or the default.
     *
     * @see Input::getValue() For retrieve the input value.
     * @see Input::setValue() For set a new value.
     * @see Input::resetValue() For reset the input value to the default state.
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

    /**
     * @return $this
     */
    public function merge(InputInterface $input): static
    {
        $this->setBreakOnFailure($input->breakOnFailure());
        if ($input instanceof Input) {
            $this->setContinueIfEmpty($input->continueIfEmpty());
        }
        $this->setErrorMessage($input->getErrorMessage());
        $name = $input->getName();
        if ($name !== null) {
            $this->setName($name);
        }
        $this->setRequired($input->isRequired());
        $this->setAllowEmpty($input->allowEmpty());
        if (! $input instanceof Input || $input->hasValue()) {
            $this->setValue($input->getRawValue());
        }

        $sourceFilterChain = $input->getFilterChain();
        $targetFilterChain = $this->getFilterChain();

        assert(
            $sourceFilterChain instanceof FilterChain
            &&
            $targetFilterChain instanceof FilterChain
        );
        $targetFilterChain->merge($sourceFilterChain);

        $sourceValidatorChain = $input->getValidatorChain();
        $targetValidatorChain = $this->getValidatorChain();
        assert(
            $sourceValidatorChain instanceof ValidatorChain
            &&
            $targetValidatorChain instanceof ValidatorChain
        );
        $targetValidatorChain->merge($sourceValidatorChain);
        return $this;
    }

    /** @inheritDoc */
    public function isValid(array|null $context = null): bool
    {
        if (is_array($this->errorMessage)) {
            $this->errorMessage = null;
        }

        /** @psalm-var mixed $value */
        $value = $this->getValue();
        $empty = $value === null || $value === '' || $value === [];

        if (! $this->hasValue && $this->hasFallback()) {
            $this->setValue($this->getFallbackValue());
            return true;
        }

        if (! $this->hasValue && ! $this->required) {
            return true;
        }

        if (! $this->hasValue) { // required, but no value
            if ($this->errorMessage === null) {
                $this->errorMessage = $this->prepareRequiredValidationFailureMessage();
            }
            return false;
        }

        if ($empty && ! $this->required && ! $this->continueIfEmpty) {
            return true;
        }

        if ($empty && $this->allowEmpty && ! $this->continueIfEmpty) {
            return true;
        }

        // At this point, we need to run validators.
        // If we do not allow empty and the "continue if empty" flag are
        // BOTH false, we inject the "not empty" validator into the chain,
        // which adds that logic into the validation routine.
        if (! $this->allowEmpty && ! $this->continueIfEmpty) {
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

    public function getMessages(): ErrorMessages
    {
        if ($this->errorMessage !== null) {
            return new ErrorMessages((array) $this->errorMessage);
        }

        if ($this->hasFallback()) {
            return new ErrorMessages([]);
        }

        $validator = $this->getValidatorChain();
        return new ErrorMessages($validator->getMessages());
    }

    protected function injectNotEmptyValidator(): void
    {
        if ((! $this->isRequired() && $this->allowEmpty()) || $this->notEmptyValidator) {
            return;
        }
        $chain = $this->getValidatorChain();
        /**
         * @todo Refactor injection of not-empty validator to use DI
         */
        assert($chain instanceof ValidatorChain);

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
     *
     * @return array<string, string>
     */
    protected function prepareRequiredValidationFailureMessage(): array
    {
        $chain = $this->getValidatorChain();
        /**
         * @todo Refactor "Empty" error message provision so that users can configure an acceptable validator,
         *       translate error messages correctly etc.
         */
        assert($chain instanceof ValidatorChain);
        $notEmpty = null;

        foreach ($chain->getValidators() as $validator) {
            if ($validator['instance'] instanceof NotEmpty) {
                $notEmpty = $validator['instance'];
                break;
            }
        }

        $validator = $notEmpty ?: $chain->plugin(NotEmpty::class);

        $validator->isValid(null);

        return $validator->getMessages();
    }
}
