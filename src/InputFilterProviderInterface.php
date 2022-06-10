<?php

namespace Laminas\InputFilter;

/**
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 * @psalm-import-type CollectionSpecification from InputFilterInterface
 */
interface InputFilterProviderInterface
{
    /**
     * Should return an array specification compatible with
     * {@link Factory::createInputFilter()}.
     *
     * @return InputFilterSpecification|CollectionSpecification
     */
    public function getInputFilterSpecification();
}
