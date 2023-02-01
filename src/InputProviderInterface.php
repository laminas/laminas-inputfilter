<?php

declare(strict_types=1);

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
     * @psalm-return InputSpecification
     * @return array
     */
    public function getInputSpecification();
}
