<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;

use function PHPUnit\Framework\assertNotNull;

final class InputFilterInterfaceStub extends InputFilter implements InputFilterInterface
{
    /**
     * @param array<string, mixed> $getRawValues
     * @param array<string, mixed> $getValues
     * @param array<string, array<array-key, string>> $getMessages
     */
    public function __construct(
        private readonly bool|null $isValid = null,
        private readonly array $getRawValues = [],
        private readonly array $getValues = [],
        private readonly array $getMessages = []
    ) {
    }

    /** @inheritDoc */
    public function isValid($context = null)
    {
        assertNotNull($this->isValid, 'isValid was not expected to be called');

        return $this->isValid;
    }

    public function getValues(): array
    {
        return $this->getValues;
    }

    public function getRawValues(): array
    {
        return $this->getRawValues;
    }

    /** @inheritDoc */
    public function getMessages(): array
    {
        return $this->getMessages;
    }
}
