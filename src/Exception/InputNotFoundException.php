<?php

declare(strict_types=1);

namespace Laminas\InputFilter\Exception;

use InvalidArgumentException;

use function sprintf;

final class InputNotFoundException extends InvalidArgumentException implements ExceptionInterface
{
    public static function forKey(string|int $key): self
    {
        return new self(sprintf(
            'The input or input filter named "%s" cannot be found',
            $key,
        ));
    }
}
