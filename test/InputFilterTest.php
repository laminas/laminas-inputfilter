<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use ArrayIterator;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @covers Laminas\InputFilter\InputFilter
 */
class InputFilterTest extends BaseInputFilterTest
{
    /**
     * @var InputFilter
     */
    protected $inputFilter;

    public function setUp()
    {
        $this->inputFilter = new InputFilter();
    }

    public function testLazilyComposesAFactoryByDefault()
    {
        $factory = $this->inputFilter->getFactory();
        $this->assertInstanceOf(Factory::class, $factory);
    }

    public function testCanComposeAFactory()
    {
        $factory = $this->createFactoryMock();
        $this->inputFilter->setFactory($factory);
        $this->assertSame($factory, $this->inputFilter->getFactory());
    }

    public function inputProvider()
    {
        $dataSets = parent::inputProvider();

        $inputSpecificationAsArray = [
            'name' => 'inputFoo',
        ];
        $inputSpecificationAsTraversable = new ArrayIterator($inputSpecificationAsArray);

        $inputSpecificationResult = new Input('inputFoo');
        $inputSpecificationResult->getFilterChain(); // Fill input with a default chain just for make the test pass
        $inputSpecificationResult->getValidatorChain(); // Fill input with a default chain just for make the test pass

        // @codingStandardsIgnoreStart
        $inputFilterDataSets = [
            // Description => [input, expected name, $expectedReturnInput]
            'array' =>       [$inputSpecificationAsArray      , 'inputFoo', $inputSpecificationResult],
            'Traversable' => [$inputSpecificationAsTraversable, 'inputFoo', $inputSpecificationResult],
        ];
        // @codingStandardsIgnoreEnd
        $dataSets = array_merge($dataSets, $inputFilterDataSets);

        return $dataSets;
    }

    /**
     * @return Factory|MockObject
     */
    protected function createFactoryMock()
    {
        /** @var Factory|MockObject $factory */
        $factory = $this->getMock(Factory::class);

        return $factory;
    }
}
