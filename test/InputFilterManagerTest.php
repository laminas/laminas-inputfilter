<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\InputFilterPluginManager;

/**
 * @group Laminas_Stdlib
 */
class InputFilterManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InputFilterPluginManager
     */
    protected $manager;

    public function setUp()
    {
        $this->manager = new InputFilterPluginManager();
    }

    public function testRegisteringInvalidElementRaisesException()
    {
        $this->setExpectedException('Laminas\InputFilter\Exception\RuntimeException');
        $this->manager->setService('test', $this);
    }

    public function testLoadingInvalidElementRaisesException()
    {
        $this->manager->setInvokableClass('test', get_class($this));
        $this->setExpectedException('Laminas\InputFilter\Exception\RuntimeException');
        $this->manager->get('test');
    }

    /**
     * @covers Laminas\InputFilter\InputFilterPluginManager::validatePlugin
     */
    public function testAllowLoadingInstancesOfInputFilterInterface()
    {
        $inputFilter = $this->getMock('Laminas\InputFilter\InputFilterInterface');

        $this->assertNull($this->manager->validatePlugin($inputFilter));
    }

    /**
     * @covers Laminas\InputFilter\InputFilterPluginManager::validatePlugin
     */
    public function testAllowLoadingInstancesOfInputInterface()
    {
        $input = $this->getMock('Laminas\InputFilter\InputInterface');

        $this->assertNull($this->manager->validatePlugin($input));
    }
}
