<?php

namespace Laminas\InputFilter;

/**
 * @psalm-import-type InputSpecification from InputFilterInterface
 */
interface InputProviderInterface
{
    /**
     * Should return an array specification compatible with
     * {@link Factory::createInput()}.
     *
     * @return InputSpecification
     */
    public function getInputSpecification();
}
