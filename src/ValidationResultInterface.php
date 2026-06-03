<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use NoDiscard;

/** @template T */
interface ValidationResultInterface
{
    #[NoDiscard]
    public function valid(): bool;

    public function getMessages(): ErrorMessages;

    public function rawValue(): mixed;

    /** @return T */
    public function value(): mixed;
}
