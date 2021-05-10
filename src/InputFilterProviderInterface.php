<?php

namespace Laminas\InputFilter;

interface InputFilterProviderInterface
{
    /**
     * Should return an array specification compatible with
     * {@link Laminas\InputFilter\Factory::createInputFilter()}.
     *
     * @return array
     */
    public function getInputFilterSpecification();
}
