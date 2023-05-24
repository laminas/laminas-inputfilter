<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Laminas\Validator\ValidatorInterface;

use function func_get_arg;
use function func_num_args;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNotNull;

final class ValidatorStub implements ValidatorInterface
{
    /** @param array<string, string> $messages */
    public function __construct(
        private readonly bool|null $isValid,
        private readonly mixed $value = 'not-set',
        private readonly array|null $context = null,
        private readonly array $messages = [],
    ) {
    }

    public function isValid(mixed $value): bool
    {
        if ($this->value !== 'not-set') {
            assertEquals($this->value, $value, 'isValid did not receive the expected value');
        }

        if (func_num_args() > 1 && $this->context !== null) {
            /** @var mixed $givenContext */
            $givenContext = func_get_arg(1);
            assertEquals(
                $this->context,
                $givenContext,
                'isValid received a context that did not match the expected context',
            );
        }

        assertNotNull($this->isValid, 'isValid was not expected to be called for this instance');

        return $this->isValid;
    }

    /** @inheritDoc */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
