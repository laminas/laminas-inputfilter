<?php

namespace Laminas\InputFilter;

/**
 * @deprecated 2.4.8 Add Laminas\Validator\NotEmpty validator to the ValidatorChain.
 */
interface EmptyContextInterface
{
    /**
     * @deprecated 2.4.8 Add Laminas\Validator\NotEmpty validator to the ValidatorChain and set this to `true`.
     *
     * @param bool $continueIfEmpty
     * @return self
     */
    public function setContinueIfEmpty($continueIfEmpty);

    /**
     * @deprecated 2.4.8 Add Laminas\Validator\NotEmpty validator to the ValidatorChain. Should always return `true`.
     *
     * @return bool
     */
    public function continueIfEmpty();
}
