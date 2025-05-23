<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

interface InputFilterAwareInterface
{
    /**
     * Set input filter
     *
     * @return $this
     */
    public function setInputFilter(InputFilterInterface $inputFilter): static;

    /**
     * Retrieve input filter
     */
    public function getInputFilter(): InputFilterInterface;
}
