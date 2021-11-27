<?php

namespace LaminasTest\InputFilter\FileInput\TestAsset;

use Laminas\Validator\ValidatorInterface;

final class FileUploadMock implements ValidatorInterface
{
    public function isValid($value)
    {
        return true;
    }

    public function getMessages()
    {
        return [];
    }
}
