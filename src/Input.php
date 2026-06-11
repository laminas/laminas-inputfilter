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
use NoDiscard;

use function array_merge;
use function assert;
use function class_exists;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;
use function reset;
use function sprintf;

/** @psalm-import-type InputSpecification from InputFilterInterface */
class Input implements MutableInputInterface
{
    protected bool $allowEmpty;
    protected bool $continueIfEmpty;
    protected bool $breakOnFailure;
    protected bool $required;

    /** @deprecated Since 3.0. This property is part of the old API and will be removed in 4.0 */
    protected mixed $value = null; // phpcs:ignore
    /** @deprecated Since 3.0. This property is part of the old API and will be removed in 4.0 */
    protected bool $hasValue;
    protected mixed $fallbackValue;
    protected bool $hasFallback;

    /**
     * @deprecated Since 3.0. This property is part of the old API and will be removed in 4.0
     *
     * @todo ArrayInput needs refactoring so that this type cannot be an array
     * @var string|array<string, string>|null
     */
    protected string|array|null $errorMessage;

    /** @deprecated Since 3.0. This property is part of the old API and will be removed in 4.0 */
    protected bool $notEmptyValidator = false;

    /**
     * @param non-empty-string|int $name
     * @param InputSpecification $options
     */
    final public function __construct(
        protected FilterChainInterface $filterChain,
        protected ValidatorChainInterface $validatorChain,
        protected string|int $name,
        array $options = [],
    ) {
        $this->assertValidName($name);
        $this->allowEmpty      = $options['allow_empty'] ?? false;
        $this->required        = $options['required'] ?? $this->allowEmpty !== true;
        $this->continueIfEmpty = $options['continue_if_empty'] ?? false;
        $this->breakOnFailure  = $options['break_on_failure'] ?? false;
        $this->errorMessage    = $options['error_message'] ?? null;
        $this->fallbackValue   = $options['fallback_value'] ?? null;
        $this->hasFallback     = $this->fallbackValue !== null;
        $this->hasValue        = false;
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

    /** @psalm-assert non-empty-string|int $name */
    private function assertValidName(mixed $name): void
    {
        if ((is_string($name) && $name !== '') || is_int($name)) {
            return;
        }

        $type = is_string($name)
            ? 'an empty string'
            : get_debug_type($name);

        throw new InvalidArgumentException(sprintf(
            'Input names must be integers or non-empty-string. Received %s',
            $type,
        ));
    }

    public function setName(string|int $name): static
    {
        $this->assertValidName($name);

        $this->name = $name;
        return $this;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;
        return $this;
    }

    /**
     * Set the input value.
     *
     * If you want to remove/unset the current value use {@link Input::resetValue()}.
     *
     * @deprecated Since 3.0. Setting the value to be validated is deprecated as part of the old stateful API.
     *             Use the {@link validate()} method instead.
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
     * @deprecated Since 3.0. This method is part of the old API and will be removed in 4.0
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
     * @deprecated Since 3.0. Error messages are included in the result of {@link validate()} and should not be
     *             retrieved from the input at runtime.
     *
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

    public function getName(): int|string
    {
        return $this->name;
    }

    /**
     * @deprecated Since 3.0. The raw, unfiltered value is included in the result of {@link validate()} and is not
     *             present when using the new validation API. This method continues to work as it did previously
     *             when using the old API.
     *
     * @inheritDoc
     */
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

    /**
     * @deprecated Since 3.0. The filtered value is included in the result of {@link validate()} and is not
     *             present when using the new validation API. This method continues to work as it did previously
     *             when using the old API.
     *
     * @inheritDoc
     */
    public function getValue(): mixed
    {
        return $this->filterChain->filter($this->value);
    }

    /**
     * Flag for inform if input value was set.
     *
     * This flag used for distinguish when {@link Input::getValue()}
     * will return the value previously set or the default.
     *
     * @deprecated Since 3.0. This method is part of the old API and will be removed in 4.0
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

    public function merge(InputInterface $input): static
    {
        $this->setBreakOnFailure($input->breakOnFailure());
        if ($input instanceof Input) {
            $this->setContinueIfEmpty($input->continueIfEmpty());
        }
        $this->setName($input->getName());
        $this->setErrorMessage($input->getErrorMessage());
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

    #[NoDiscard]
    public function validate(mixed $value, array $context): InputValidationResult
    {
        $isEmpty = $value === '' || $value === null || $value === [];
        /** @psalm-var mixed $resolvedValue */
        $resolvedValue = $isEmpty && $this->hasFallback ? $this->fallbackValue : $value;
        /**
         * Behaviour Change: The fallback value is filtered where previously it was returned verbatim
         *
         * @psalm-var mixed $filteredValue
         */
        $filteredValue = $this->filterChain->filter($resolvedValue);

        if (
            // We have a valid result when a value is empty, but a fallback is present
            ($isEmpty && $this->hasFallback)
            ||
            // Empty values are valid when they are not required and validation should not continue for empty values
            ($isEmpty && ! $this->required && ! $this->continueIfEmpty)
            ||
            // Empty is valid when allowEmpty is true and continue if empty is false
            ($isEmpty && $this->allowEmpty && ! $this->continueIfEmpty)
        ) {
            return InputValidationResult::pass($this->name, $value, $filteredValue);
        }

        $isValid  = $this->validatorChain->isValid($filteredValue, $context);
        $messages = $this->validatorChain->getMessages();

        /**
         * An empty value should not be considered valid in this situation, regardless
         * of what the validator chain says.
         * Instead of mutating the chain, fail validation with a validation failure message that advises the user to
         * customise the validation chain with a NotEmpty validator.
         */
        if ($isValid && $isEmpty) {
            $isValid  = false;
            $messages = array_merge([
                InputInterface::EMPTY_FAILURE_VALIDATION_KEY => sprintf(
                    'The value for "%s" was empty, but its configuration prohibits an empty value. '
                    . 'Prepend a "NotEmpty" validator to this input’s chain in order to customise '
                    . 'this validation failure message',
                    $this->name,
                ),
            ], $messages);
        }

        return $isValid
            ? InputValidationResult::pass($this->name, $value, $filteredValue)
            : InputValidationResult::fail(
                $this->name,
                $value,
                $filteredValue,
                new ErrorMessages($messages),
            );
    }

    /**
     * @deprecated Since 3.0. Please migrate to the new validation method {@link validate()} that returns a result
     *             object instead of a boolean, and does not mutate internal state.
     *
     * @inheritDoc
     */
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

    /**
     * @deprecated Since 3.0. Error messages are included in the result of {@link validate()} and should not be
     *             retrieved from the input at runtime.
     *
     * @inheritDoc
     */
    public function getMessages(): ErrorMessages
    {
        if ($this->errorMessage !== null) {
            return new ErrorMessages((array) $this->errorMessage);
        }

        if ($this->hasFallback()) {
            return new ErrorMessages([]);
        }

        return new ErrorMessages($this->validatorChain->getMessages());
    }

    /**
     * @deprecated Since 3.0. This method is part of the old API and will be removed in 4.0
     */
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
     * @deprecated Since 3.0. This method is part of the old API and will be removed in 4.0
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
