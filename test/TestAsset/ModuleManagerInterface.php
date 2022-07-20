<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

interface ModuleManagerInterface
{
    public function getEvent(): ModuleEventInterface;
}
