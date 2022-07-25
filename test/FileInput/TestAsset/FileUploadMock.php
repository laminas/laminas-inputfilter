<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput\TestAsset;

use Laminas\Validator\ValidatorInterface;

final class FileUploadMock implements ValidatorInterface
{
    /**
     * @param mixed $value
     * @return bool
     */
    public function isValid($value)
    {
        return true;
    }

    /** @return array<string, string> */
    public function getMessages()
    {
        return [];
    }
}
