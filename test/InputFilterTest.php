<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-import-type InputSpecification from InputFilterInterface
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 */
#[CoversClass(InputFilter::class)]
final class InputFilterTest extends TestCase
{
    private Factory $factory;

    protected function setUp(): void
    {
        $this->factory = TestHelper::createInputFilterFactory();
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
}
