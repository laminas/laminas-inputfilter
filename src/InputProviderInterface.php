<?php

namespace Laminas\InputFilter;

interface InputProviderInterface
{
    /**
     * Should return an array specification compatible with
     * {@link Laminas\InputFilter\Factory::createInput()}.
     *
     * @return array
     */
    public function getInputSpecification();
}
