<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Laminas\InputFilter\ErrorMessages;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilter;

use function array_map;
use function PHPUnit\Framework\assertNotNull;

/**
 * @extends InputFilter<array<array-key, mixed>>
 */
final class InputFilterInterfaceStub extends InputFilter
{
    /**
     * @param array<string, mixed> $getRawValues
     * @param array<string, mixed> $getValues
     * @param array<string, array<array-key, string>> $getMessages
     */
    public function __construct(
        Factory $factory,
        private readonly bool|null $isValid = null,
        private readonly array $getRawValues = [],
        private readonly array $getValues = [],
        private readonly array $getMessages = []
    ) {
        parent::__construct($factory);
    }

    /** @inheritDoc */
    public function isValid(array|null $context = null): bool
    {
        assertNotNull($this->isValid, 'isValid was not expected to be called');

        return $this->isValid;
    }

    /** @inheritDoc */
    public function getValues(): array
    {
        return $this->getValues;
    }

    /** @inheritDoc */
    public function getRawValues(): array
    {
        return $this->getRawValues;
    }

    /** @inheritDoc */
    public function getMessages(): ErrorMessages
    {
        return new ErrorMessages(array_map(
            static fn (array $messages): ErrorMessages => new ErrorMessages($messages),
            $this->getMessages,
        ));
    }
}
