<?php

namespace Laminas\InputFilter;

interface InputFilterAwareInterface
{
    /**
     * Set input filter
     *
     * @return InputFilterAwareInterface
     */
    public function setInputFilter(InputFilterInterface $inputFilter);

    /**
     * Retrieve input filter
     *
     * @return InputFilterInterface
     */
    public function getInputFilter();
}
