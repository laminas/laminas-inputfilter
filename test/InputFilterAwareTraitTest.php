<?php

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterAwareTrait;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 * @requires PHP 5.4
 * @covers \Laminas\InputFilter\InputFilterAwareTrait
 */
class InputFilterAwareTraitTest extends TestCase
{
    public function testSetInputFilter(): void
    {
        $object = $this->getObjectForTrait(InputFilterAwareTrait::class);

        $r = new ReflectionObject($object);
        $p = $r->getProperty('inputFilter');
        $p->setAccessible(true);
        $this->assertNull($p->getValue($object));

        $inputFilter = new InputFilter();

        $object->setInputFilter($inputFilter);

        $this->assertSame($inputFilter, $p->getValue($object));
    }

    public function testGetInputFilter(): void
    {
        $object = $this->getObjectForTrait(InputFilterAwareTrait::class);

        $this->assertNull($object->getInputFilter());

        $inputFilter = new InputFilter();

        $object->setInputFilter($inputFilter);

        $this->assertEquals($inputFilter, $object->getInputFilter());
    }
}
