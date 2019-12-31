<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\InputFilter;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * @requires PHP 5.4
 */
class InputFilterAwareTraitTest extends TestCase
{
    public function testSetInputFilter()
    {
        $object = $this->getObjectForTrait('\Laminas\InputFilter\InputFilterAwareTrait');

        $this->assertAttributeEquals(null, 'inputFilter', $object);

        $inputFilter = new InputFilter;

        $object->setInputFilter($inputFilter);

        $this->assertAttributeEquals($inputFilter, 'inputFilter', $object);
    }

    public function testGetInputFilter()
    {
        $object = $this->getObjectForTrait('\Laminas\InputFilter\InputFilterAwareTrait');

        $this->assertNull($object->getInputFilter());

        $inputFilter = new InputFilter;

        $object->setInputFilter($inputFilter);

        $this->assertEquals($inputFilter, $object->getInputFilter());
    }
}
