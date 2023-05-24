<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

use Exception;
use Psr\Http\Message\UploadedFileInterface;

use const UPLOAD_ERR_OK;

final class UploadedFileInterfaceStub implements UploadedFileInterface
{
    public function __construct(
        private readonly int $expectedErrorCode = UPLOAD_ERR_OK
    ) {
    }

    public function getStream(): never
    {
        throw new Exception('Not Implemented');
    }

    public function moveTo(string $targetPath): never
    {
        throw new Exception('Not Implemented');
    }

    public function getSize(): never
    {
        throw new Exception('Not Implemented');
    }

    public function getError(): int
    {
        return $this->expectedErrorCode;
    }

    public function getClientFilename(): never
    {
        throw new Exception('Not Implemented');
    }

    public function getClientMediaType(): never
    {
        throw new Exception('Not Implemented');
    }
}
