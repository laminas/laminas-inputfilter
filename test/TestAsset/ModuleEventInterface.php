<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\TestAsset;

/**
 * Mock interface to use when testing Module::init
 *
 * Mimics Laminas\ModuleManager\ModuleEvent methods called.
 */
interface ModuleEventInterface
{
    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getParam($name, $default = null);
}
