<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\InputFilter;

/**
 * Implementors of this interface may report on the existence of unknown input,
 * as well as retrieve all unknown values.
 */
interface UnknownInputsCapableInterface
{
    public function hasUnknown();
    public function getUnknown();
}
