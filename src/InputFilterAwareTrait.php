<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

trait InputFilterAwareTrait
{
    protected ?InputFilterInterface $inputFilter;

    /**
     * Set input filter
     *
     * @return $this
     */
    public function setInputFilter(InputFilterInterface $inputFilter): static
    {
        $this->inputFilter = $inputFilter;

        return $this;
    }

    /**
     * Retrieve input filter
     */
    public function getInputFilter(): InputFilterInterface
    {
        return $this->inputFilter;
    }
}
