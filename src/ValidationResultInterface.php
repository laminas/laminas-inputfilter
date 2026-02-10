<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

interface ValidationResultInterface
{
    public function valid(): bool;

    public function getMessages(): ErrorMessages;

    public function rawValue(): mixed;

    public function value(): mixed;
}
