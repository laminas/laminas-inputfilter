<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

/**
 * Ensures Inputs store unfiltered data and are capable of returning it
 *
 * @deprecated Since 3.0. Input filters will no longer store payloads from version 4.0
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
