<?php

namespace Laminas\InputFilter;

/**
 * Mark an input as able to be replaced by another when merging input filters.
 *
 */
interface ReplaceableInputInterface
{
    /**
     * @param $input
     * @param $name
     * @return self
     */
    public function replace($input, $name);
}
