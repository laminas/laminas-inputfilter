<?php

namespace Laminas\InputFilter;

/**
 * Ensures Inputs store unfiltered data and are capable of returning it
 *
 * @psalm-import-type InputData from InputFilterInterface
 */
interface UnfilteredDataInterface
{
    /**
     * @return InputData
     */
    public function getUnfilteredData();

    /**
     * @param InputData $data
     * @return $this
     */
    public function setUnfilteredData($data);
}
