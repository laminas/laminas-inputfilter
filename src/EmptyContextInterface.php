<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

interface EmptyContextInterface
{
    public function setContinueIfEmpty(bool $continueIfEmpty): static;

    public function continueIfEmpty(): bool;
}
