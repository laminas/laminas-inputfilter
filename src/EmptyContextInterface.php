<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

interface EmptyContextInterface
{
    public function setContinueIfEmpty(bool $continueIfEmpty): EmptyContextInterface;

    public function continueIfEmpty(): bool;
}
