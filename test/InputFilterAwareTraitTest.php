<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterAwareTrait;
use LaminasTest\InputFilter\TestAsset\InputFilterAware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

#[CoversClass(InputFilterAwareTrait::class)]
final class InputFilterAwareTraitTest extends TestCase
{
    private Factory $factory;

    protected function setUp(): void
    {
        $this->factory = FactoryTestHelper::createInputFilterFactory();
    }

    public function testSetInputFilter(): void
    {
        $object = new InputFilterAware();

        $r = new ReflectionObject($object);
        $p = $r->getProperty('inputFilter');
        $this->assertNull($p->getValue($object));

        $inputFilter = new InputFilter($this->factory);

        $object->setInputFilter($inputFilter);

        $this->assertSame($inputFilter, $p->getValue($object));
    }

    public function testGetInputFilter(): void
    {
        $object = new InputFilterAware();

        $this->assertNull($object->getInputFilter());

        $inputFilter = new InputFilter($this->factory);

        $object->setInputFilter($inputFilter);

        $this->assertEquals($inputFilter, $object->getInputFilter());
    }
}
