<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

/**
 * Implementors of this interface may report on the existence of unknown input,
 * as well as retrieve all unknown values.
 */
interface UnknownInputsCapableInterface
{
    /**
     * Does the data set contain unknown inputs?
     *
     * @throws Exception\RuntimeException
     */
    public function hasUnknown(): bool;

    /**
     * Return the unknown input
     *
     * @throws Exception\RuntimeException
     * @return array<array-key, mixed>
     */
    public function getUnknown(): array;
}
