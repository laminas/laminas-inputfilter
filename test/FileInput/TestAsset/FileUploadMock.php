<?php

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

    /** @return array */
    public function getMessages()
    {
        return [];
    }
}
