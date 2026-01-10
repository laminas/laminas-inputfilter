<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

/**
 * Ensures Inputs store unfiltered data and are capable of returning it
 */
interface UnfilteredDataInterface
{
    /**
     * @return array<array-key, mixed>
     */
    public function getUnfilteredData(): array;

    /**
     * @param array<array-key, mixed> $data
     * @return $this
     */
    public function setUnfilteredData(array $data): static;
}
