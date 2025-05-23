<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Validator\IsArray;

use function array_map;
use function assert;
use function is_array;

/** @final */
class ArrayInput extends Input
{
    public function getValue(): mixed
    {
        if (! is_array($this->value)) {
            return $this->value;
        }

        $filter = $this->getFilterChain();

        return array_map(
            static fn (mixed $value): mixed => $filter->filter($value),
            $this->value,
        );
    }

    public function isValid(mixed $context = null): bool
    {
        $hasValue    = $this->hasValue();
        $required    = $this->isRequired();
        $hasFallback = $this->hasFallback();

        if (! $hasValue && $hasFallback) {
            $this->setValue($this->getFallbackValue());
            return true;
        }

        if (! $hasValue && $required) {
            if ($this->errorMessage === null) {
                $this->errorMessage = $this->prepareRequiredValidationFailureMessage();
            }
            return false;
        }

        if (! $hasValue && ! $required) {
            return true;
        }

        if (! $this->continueIfEmpty() && ! $this->allowEmpty()) {
            $this->injectNotEmptyValidator();
        }

        $values = $this->getValue();

        if (! is_array($values)) {
            $this->errorMessage = $this->prepareNotArrayFailureMessage();

            return false;
        }

        $validator = $this->getValidatorChain();
        $result    = true;

        if ($required && empty($values)) {
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
                if ($hasFallback) {
                    $this->setValue($this->getFallbackValue());
                    return true;
                }
                break;
            }
        }

        return $result;
    }

    private function prepareNotArrayFailureMessage(): ?string
    {
        $chain   = $this->getValidatorChain();
        $isArray = $chain->plugin(IsArray::class);

        foreach ($chain->getValidators() as $validator) {
            if ($validator['instance'] instanceof IsArray) {
                $isArray = $validator['instance'];
                break;
            }
        }

        $result = $isArray->isValid($this->getValue());
        assert($result === false);

        return $isArray->getMessages()[IsArray::NOT_ARRAY] ?? null;
    }
}
