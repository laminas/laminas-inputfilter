<?php

namespace Laminas\InputFilter;

interface InputFilterProviderInterface
{
    /**
     * Should return an array specification compatible with
     * {@link Factory::createInputFilter()}.
     *
     * @return array
     */
    public function getInputFilterSpecification();
}
