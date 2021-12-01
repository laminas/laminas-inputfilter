<?php

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
