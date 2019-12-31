<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter\TestAsset;

/**
 * Mock interface to use when testing Module::init
 *
 * Mimics Laminas\ModuleManager\ModuleEvent methods called.
 */
interface ModuleEventInterface
{
    public function getParam($name, $default = null);
}
