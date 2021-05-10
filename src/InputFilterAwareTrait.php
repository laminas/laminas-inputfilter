<?php

namespace Laminas\InputFilter;

trait InputFilterAwareTrait
{
    /**
     * @var InputFilterInterface
     */
    protected $inputFilter = null;

    /**
     * Set input filter
     *
     * @param InputFilterInterface $inputFilter
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
