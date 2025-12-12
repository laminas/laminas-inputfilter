<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

/**
 * Mark an input as able to be replaced by another when merging input filters.
 */
interface ReplaceableInputInterface
{
    public function replace(
        InputInterface|InputFilterInterface|array $input,
        int|string $name,
    ): static;
}
