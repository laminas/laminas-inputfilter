<?php

namespace Laminas\InputFilter;

use function gettype;
use function is_array;
use function sprintf;

class ArrayInput extends Input
{
    /** @var array<array-key, mixed> */
    protected $value = [];

    /**
     * @inheritDoc
     * @param  array<array-key, mixed> $value
     * @throws Exception\InvalidArgumentException
     */
    public function setValue($value)
    {
        if (! is_array($value)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Value must be an array, %s given.',
                gettype($value)
            ));
        }
        parent::setValue($value);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resetValue()
    {
        $this->value    = [];
        $this->hasValue = false;
        return $this;
    }

    /**
     * @return array
     */
    public function getValue()
    {
        $filter = $this->getFilterChain();
        $result = [];
        foreach ($this->value as $key => $value) {
            $result[$key] = $filter->filter($value);
        }
        return $result;
    }

    /**
     * @param  mixed $context Extra "context" to provide the validator
     * @return bool
     */
    public function isValid($context = null)
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

        if (! $this->continueIfEmpty() && ! $this->allowEmpty()) {
            $this->injectNotEmptyValidator();
        }
        $validator = $this->getValidatorChain();
        $values    = $this->getValue();
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
}
