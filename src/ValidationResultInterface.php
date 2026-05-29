<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

/** @template T */
interface ValidationResultInterface
{
    public function valid(): bool;

    public function getMessages(): ErrorMessages;

    public function rawValue(): mixed;

    /** @return T */
    public function value(): mixed;
}
