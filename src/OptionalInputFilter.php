<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

/**
 * InputFilter which only checks the containing Inputs when non-empty data is set,
 * else it reports valid
 * This is analog to {@see Input} with the option ->setRequired(false)
 *
 * @template TFilteredValues
 * @extends InputFilter<TFilteredValues>
 */
class OptionalInputFilter extends InputFilter
{
    /**
     * Set data to use when validating and filtering
     *
     * @param iterable|null $data must be a non-empty iterable in order trigger
     *                            actual validation, else it is always valid
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setData($data)
    {
        parent::setData($data ?: []);

        return $this;
    }

    /**
     * Run validation, or return true if the data was empty
     *
     * {@inheritDoc}
     */
    public function isValid($context = null)
    {
        if ($this->data) {
            return parent::isValid($context);
        }

        return true;
    }

    /**
     * Return a list of filtered values, or null if the data was missing entirely
     * Null is returned instead of an empty array to prevent it being passed to a hydrator,
     *     which would likely cause failures later on in your program
     * Fallbacks for the inputs are not respected by design
     *
     * @return TFilteredValues|null
     */
    public function getValues()
    {
        return $this->data ? parent::getValues() : null;
    }
}
