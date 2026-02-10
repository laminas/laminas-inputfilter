<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\InputFilter\Exception\InputNotFoundException;

final readonly class InputFilterValidationResult implements ValidationResultInterface
{
    /** @param array<array-key, self|InputValidationResult> $results */
    public function __construct(
        public array $results,
    ) {
    }

    public function valid(): bool
    {
        foreach ($this->results as $result) {
            if ($result->valid()) {
                continue;
            }

            return false;
        }

        return true;
    }

    public function getMessages(): ErrorMessages
    {
        $messages = [];
        foreach ($this->results as $key => $result) {
            $name            = $this->keyName($key, $result);
            $messages[$name] = $result->getMessages();
        }

        return new ErrorMessages($messages);
    }

    /** @psalm-suppress MixedAssignment */
    public function rawValue(): array
    {
        $value = [];
        foreach ($this->results as $key => $result) {
            $name         = $this->keyName($key, $result);
            $value[$name] = $result->rawValue();
        }

        return $value;
    }

    /** @psalm-suppress MixedAssignment */
    public function value(): array
    {
        $value = [];
        foreach ($this->results as $key => $result) {
            $name         = $this->keyName($key, $result);
            $value[$name] = $result->value();
        }

        return $value;
    }

    /** @throws InputNotFoundException */
    public function resultFor(string|int $key): ValidationResultInterface
    {
        $result = $this->results[$key] ?? null;
        if (! $result instanceof ValidationResultInterface) {
            throw InputNotFoundException::forKey($key);
        }

        return $result;
    }

    private function keyName(string|int $key, ValidationResultInterface $result): string|int
    {
        return $result instanceof InputValidationResult
            ? $result->name()
            : $key;
    }
}
