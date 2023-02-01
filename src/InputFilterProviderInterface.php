<?php

declare(strict_types=1);

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
     * @psalm-return InputFilterSpecification|CollectionSpecification
     * @return array
     */
    public function getInputFilterSpecification();
}
