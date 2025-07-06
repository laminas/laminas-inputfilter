<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput\TestAsset;

use Laminas\Validator\ValidatorInterface;

final class FileUploadMock implements ValidatorInterface
{
    /**
     * @param mixed $value
     */
    public function isValid($value): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function getMessages()
    {
        return [];
    }
}
