<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

/**
 * @template T
 * @implements ValidationResultInterface<T>
 */
final readonly class InputValidationResult implements ValidationResultInterface
{
    /** @param T $value */
    private function __construct(
        private int|string $name,
        private mixed $rawValue,
        private mixed $value,
        private bool $valid,
        private ErrorMessages $errorMessages,
    ) {
    }

    /**
     * @param T1 $value
     * @return self<T1>
     * @template T1
     */
    public static function pass(
        int|string $name,
        mixed $rawValue,
        mixed $value,
    ): self {
        return new self($name, $rawValue, $value, true, new ErrorMessages([]));
    }

    public static function fail(
        int|string $name,
        mixed $rawValue,
        mixed $value,
        ErrorMessages $errorMessages,
    ): self {
        return new self($name, $rawValue, $value, false, $errorMessages);
    }

    public function name(): int|string
    {
        return $this->name;
    }

    public function valid(): bool
    {
        return $this->valid;
    }

    public function getMessages(): ErrorMessages
    {
        return $this->errorMessages;
    }

    public function rawValue(): mixed
    {
        return $this->rawValue;
    }

    public function value(): mixed
    {
        return $this->value;
    }
}
