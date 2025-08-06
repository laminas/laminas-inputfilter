<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use ArrayIterator;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use Traversable;

use function array_merge;

#[CoversClass(InputFilter::class)]
final class InputFilterTest extends BaseInputFilterTest
{
    /** @var InputFilter $inputFilter */
    protected $inputFilter;

    protected function setUp(): void
    {
        $this->factory = TestHelper::createInputFilterFactory();

        $this->inputFilter = new InputFilter($this->factory);
    }

    /**
     * @psalm-return array<string, array{
     *     0: array|Traversable,
     *     1: string,
     *     2: Input
     * }>
     */
    public static function inputProvider(): array
    {
        $dataSets = parent::inputProvider();

        $inputSpecificationAsArray       = [
            'name' => 'inputFoo',
        ];
        $inputSpecificationAsTraversable = new ArrayIterator($inputSpecificationAsArray);

        $inputSpecificationResult = TestHelper::createInputFilterFactory()->createInput([
            'name' => 'inputFoo',
        ]);

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
     * Particularly in APIs, a null value may be passed for a set of data
     * rather than an object or array. This ensures that doing so will
     * work consistently with passing an empty array.
     *
     * @see https://github.com/zendframework/zend-inputfilter/issues/159
     */
    public function testNestedInputFilterShouldAllowNullValueForData(): void
    {
        $filter1 = new InputFilter($this->factory);
        $filter1->add([
            'type'         => InputFilter::class,
            'nestedField1' => [
                'required' => false,
            ],
        ], 'nested');

        $expect = ['nested' => ['nestedField1' => null]];

        // Empty set of data
        $filter1->setData([]);
        self::assertEquals($expect, $filter1->getValues());

        // null provided for nested filter
        $filter1->setData(['nested' => null]);
        self::assertEquals($expect, $filter1->getValues());
    }

    public function testInputsWithoutANameYieldMergedInputsWithAnEmptyName(): void
    {
        $a = new Input(TestHelper::createFilterChain(), TestHelper::createValidatorChain());
        $b = new Input(TestHelper::createFilterChain(), TestHelper::createValidatorChain());

        $filter = new InputFilter(
            $this->factory
        );
        $filter->add($a);
        $filter->add($b);

        self::assertCount(1, $filter->getInputs());
        self::assertSame($a, $filter->get(''));
    }
}
