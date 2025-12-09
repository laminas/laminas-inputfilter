<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Stdlib\ArrayUtils;

use function is_iterable;

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
    public function setData($data): static
    {
        parent::setData($this->isEmpty($data) ? [] : $data);

        return $this;
    }

    /**
     * Run validation, or return true if the data was empty
     *
     * {@inheritDoc}
     */
    public function isValid($context = null): bool
    {
        if (! $this->isEmpty($this->data)) {
            return parent::isValid($context);
        }

        return true;
    }

    private function isEmpty(iterable|null $data): bool
    {
        $data = is_iterable($data) ? ArrayUtils::iteratorToArray($data) : $data;

        return $data === [] || $data === null;
    }
}
