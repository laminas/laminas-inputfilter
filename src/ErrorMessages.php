<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Laminas\InputFilter\Exception\BadMethodCallException;
use Traversable;

/**
 * @psalm-type ValueType = string|self
 * @implements ArrayAccess<array-key, ValueType>
 * @implements IteratorAggregate<array-key, ValueType>
 * @immutable
 */
final readonly class ErrorMessages implements Countable, IteratorAggregate, ArrayAccess, JsonSerializable
{
    /**
     * @param array<array-key, ValueType> $messages
     */
    public function __construct(
        private array $messages,
    ) {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->messages);
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->messages as $message) {
            if ($message instanceof self) {
                $count += $message->count();
            } else {
                $count++;
            }
        }

        return $count;
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->messages as $key => $message) {
            if ($message instanceof self) {
                $result[$key] = $message->toArray();
            } else {
                $result[$key] = $message;
            }
        }

        return $result;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->messages[$offset]);
    }

    public function offsetGet(mixed $offset): string|self|null
    {
        return $this->messages[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new BadMethodCallException('Error messages are immutable');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new BadMethodCallException('Error messages are immutable');
    }

    /** @return array<array-key, ValueType> */
    public function jsonSerialize(): array
    {
        return $this->messages;
    }
}
