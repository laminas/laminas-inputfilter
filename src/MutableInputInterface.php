<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

interface MutableInputInterface extends InputInterface
{
    public function setAllowEmpty(bool $allowEmpty): static;

    public function setBreakOnFailure(bool $breakOnFailure): static;

    public function setErrorMessage(string|null $errorMessage): static;

    /** @param non-empty-string|int $name */
    public function setName(string|int $name): static;

    public function setRequired(bool $required): static;

    public function merge(InputInterface $input): static;

    public function setContinueIfEmpty(bool $continueIfEmpty): static;

    public function setFallbackValue(mixed $value): static;

    public function clearFallbackValue(): void;
}
