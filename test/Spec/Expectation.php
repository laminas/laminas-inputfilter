<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\Spec;

final readonly class Expectation
{
    /**
     * @param iterable<array-key, mixed> $input
     * @param list<string> $invalidKeys
     * @param list<string> $validKeys
     * @param array<array-key, mixed> $expect
     */
    public function __construct(
        public iterable $input,
        public bool $valid,
        public array $invalidKeys,
        public array $validKeys,
        public array $expect,
    ) {
    }
}
