<?php

namespace LaminasTest\InputFilter;

use ArrayIterator;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use PHPUnit\Framework\MockObject\MockObject;

use function array_merge;

/**
 * @covers \Laminas\InputFilter\InputFilter
 */
class InputFilterTest extends BaseInputFilterTest
{
    /** @var InputFilter */
    protected $inputFilter;

    protected function setUp(): void
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

    /**
     * @psalm-return array<string, array{
     *     0: array|Traversable,
     *     1: string,
     *     2: Input
     * }>
     */
    public function inputProvider(): array
    {
        $dataSets = parent::inputProvider();

        $inputSpecificationAsArray       = [
            'name' => 'inputFoo',
        ];
        $inputSpecificationAsTraversable = new ArrayIterator($inputSpecificationAsArray);

        $inputSpecificationResult = new Input('inputFoo');
        $inputSpecificationResult->getFilterChain(); // Fill input with a default chain just for make the test pass
        $inputSpecificationResult->getValidatorChain(); // Fill input with a default chain just for make the test pass

        // phpcs:disable
        $inputFilterDataSets = [
            // Description => [input, expected name, $expectedReturnInput]
            'array' =>       [$inputSpecificationAsArray      , 'inputFoo', $inputSpecificationResult],
            'Traversable' => [$inputSpecificationAsTraversable, 'inputFoo', $inputSpecificationResult],
        ];
        // phpcs:enable
        $dataSets = array_merge($dataSets, $inputFilterDataSets);

        return $dataSets;
    }

    /**
     * @return Factory|MockObject
     */
    protected function createFactoryMock()
    {
        /** @var Factory|MockObject $factory */
        $factory = $this->createMock(Factory::class);

        return $factory;
    }

    /**
     * Particularly in APIs, a null value may be passed for a set of data
     * rather than an object or array. This ensures that doing so will
     * work consistently with passing an empty array.
     *
     * @see https://github.com/zendframework/zend-inputfilter/issues/159
     */
    public function testNestedInputFilterShouldAllowNullValueForData()
    {
        $filter1 = new InputFilter();
        $filter1->add([
            'type'         => InputFilter::class,
            'nestedField1' => [
                'required' => false,
            ],
        ], 'nested');

        // Empty set of data
        $filter1->setData([]);
        self::assertNull($filter1->getValues()['nested']['nestedField1']);

        // null provided for nested filter
        $filter1->setData(['nested' => null]);
        self::assertNull($filter1->getValues()['nested']['nestedField1']);
    }
}
