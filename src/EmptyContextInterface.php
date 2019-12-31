<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\InputFilter;

interface EmptyContextInterface
{
    /**
     * @param bool $continueIfEmpty
     * @return self
     */
    public function setContinueIfEmpty($continueIfEmpty);

    /**
     * @return bool
     */
    public function continueIfEmpty();
}
