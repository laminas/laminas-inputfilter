<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

interface InputFilterInputInterface
{
    public function isValid(): bool;

    /**
     * @return array<array-key, string>
     */
    public function getMessages(): array;
}
