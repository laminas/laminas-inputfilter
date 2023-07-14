<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput\TestAsset;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\Stdlib\InitializableInterface;

/** @extends InputFilterInterface<array<array-key, mixed>> */
interface InitializableInputFilterInterface extends InputFilterInterface, InitializableInterface
{
}
