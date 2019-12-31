<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterAwareTrait;
use PHPUnit\Framework\TestCase;

/**
 * @requires PHP 5.4
 * @covers Laminas\InputFilter\InputFilterAwareTrait
 */
class InputFilterAwareTraitTest extends TestCase
{
    public function testSetInputFilter()
    {
        $object = $this->getObjectForTrait(InputFilterAwareTrait::class);

        $this->assertAttributeEquals(null, 'inputFilter', $object);

        $inputFilter = new InputFilter;

        $object->setInputFilter($inputFilter);

        $this->assertAttributeEquals($inputFilter, 'inputFilter', $object);
    }

    public function testGetInputFilter()
    {
        $object = $this->getObjectForTrait(InputFilterAwareTrait::class);

        $this->assertNull($object->getInputFilter());

        $inputFilter = new InputFilter;

        $object->setInputFilter($inputFilter);

        $this->assertEquals($inputFilter, $object->getInputFilter());
    }
}
