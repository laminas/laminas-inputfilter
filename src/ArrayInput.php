<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Validator\IsArray;
use Laminas\Validator\ValidatorChain;

use function array_map;
use function assert;
use function is_array;

/** @final */
class ArrayInput extends Input
{
    /** @deprecated since 2.30.1 The default value should be null as in parent `Input` */
    protected mixed $value = [];

    /**
     * @deprecated since 2.30.1 Once the default value is null, this method is no longer required
     *
     * @inheritDoc
     */
    public function resetValue(): static
    {
        $this->value    = [];
        $this->hasValue = false;
        return $this;
    }

    public function getValue(): mixed
    {
        if (! is_array($this->value)) {
            return $this->value;
        }

        $filter = $this->getFilterChain();

        return array_map(
            $filter->filter(...),
            $this->value,
        );
    }

    /** @inheritDoc */
    public function isValid(?array $context = null): bool
    {
        if (! $this->hasValue && $this->hasFallback) {
            $this->setValue($this->getFallbackValue());
            return true;
        }

        if (! $this->hasValue && $this->required) {
            if ($this->errorMessage === null) {
                $this->errorMessage = $this->prepareRequiredValidationFailureMessage();
            }
            return false;
        }

        if (! $this->hasValue) {
            return true;
        }

        if (! $this->continueIfEmpty && ! $this->allowEmpty) {
            $this->injectNotEmptyValidator();
        }

        $values = $this->getValue();

        if (! is_array($values)) {
            $this->errorMessage = $this->prepareNotArrayFailureMessage();

            return false;
        }

        $validator = $this->getValidatorChain();
        $result    = true;

        if ($this->required && empty($values)) {
            if ($this->errorMessage === null) {
                $this->errorMessage = $this->prepareRequiredValidationFailureMessage();
            }
            return false;
        }

        foreach ($values as $value) {
            $empty = $value === null || $value === '' || $value === [];
            if ($empty && ! $this->isRequired() && ! $this->continueIfEmpty()) {
                $result = true;
                continue;
            }
            if ($empty && $this->allowEmpty() && ! $this->continueIfEmpty()) {
                $result = true;
                continue;
            }
            $result = $validator->isValid($value, $context);
            if (! $result) {
                if ($this->hasFallback) {
                    $this->setValue($this->getFallbackValue());
                    return true;
                }
                break;
            }
        }

        return $result;
    }

    /** @return array<string, string> */
    private function prepareNotArrayFailureMessage(): array
    {
        $chain = $this->getValidatorChain();
        /**
         * @todo We cannot call half these methods on ValidatorChainInterface, so we must devise a better way
         *       of configuring the 'Not Array' error messages, and, allowing users to translate these error messages.
         */
        assert($chain instanceof ValidatorChain);
        $isArray = $chain->plugin(IsArray::class);

        foreach ($chain->getValidators() as $validator) {
            if ($validator['instance'] instanceof IsArray) {
                $isArray = $validator['instance'];
                break;
            }
        }

        $result = $isArray->isValid($this->getValue());
        assert($result === false);

        return $isArray->getMessages();
    }
}
