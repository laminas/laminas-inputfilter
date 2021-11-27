<?php

namespace Laminas\InputFilter;

/**
 * Ensures Inputs store unfiltered data and are capable of returning it
 */
interface UnfilteredDataInterface
{
    /**
     * @return array|object
     */
    public function getUnfilteredData();

    /**
     * @param array|object $data
     * @return $this
     */
    public function setUnfilteredData($data);
}
