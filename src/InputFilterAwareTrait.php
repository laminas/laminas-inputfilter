<?php

namespace Laminas\InputFilter;

trait InputFilterAwareTrait
{
    /** @var InputFilterInterface */
    protected $inputFilter;

    /**
     * Set input filter
     *
     * @return mixed
     */
    public function setInputFilter(InputFilterInterface $inputFilter)
    {
        $this->inputFilter = $inputFilter;

        return $this;
    }

    /**
     * Retrieve input filter
     *
     * @return InputFilterInterface
     */
    public function getInputFilter()
    {
        return $this->inputFilter;
    }
}
