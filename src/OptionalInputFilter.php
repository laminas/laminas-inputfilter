<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Stdlib\ArrayUtils;
use NoDiscard;

use function is_iterable;

/**
 * InputFilter which only checks the containing Inputs when non-empty data is set,
 * else it reports valid
 * This is analogous to {@see Input} with the option ->setRequired(false)
 *
 * @template TFilteredValues
 * @extends InputFilter<TFilteredValues>
 */
class OptionalInputFilter extends InputFilter
{
    /**
     * @deprecated Since 3.0. This method is part of the old API and will be removed in 4.0
     *
     * @inheritDoc
     */
    public function setData(iterable|null $data): static
    {
        parent::setData($this->isEmpty($data) ? [] : $data);

        return $this;
    }

    /**
     * Run validation, or return true if the data was empty
     *
     * @deprecated Since 3.0. This method is part of the old API and will be removed in 4.0
     *
     * {@inheritDoc}
     */
    public function isValid(array|null $context = null): bool
    {
        if (! $this->isEmpty($this->data)) {
            return parent::isValid($context);
        }

        return true;
    }

    /** @inheritDoc */
    #[NoDiscard]
    public function validate(iterable $data, array $context = []): InputFilterValidationResult
    {
        if ($this->isEmpty($data)) {
            return new InputFilterValidationResult([]);
        }

        return parent::validate($data, $context);
    }

    private function isEmpty(iterable|null $data): bool
    {
        $data = is_iterable($data) ? ArrayUtils::iteratorToArray($data) : $data;

        return $data === [] || $data === null;
    }
}
