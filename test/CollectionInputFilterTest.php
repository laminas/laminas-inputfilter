<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps

namespace LaminasTest\InputFilter;

use ArrayIterator;
use ArrayObject;
use Laminas\InputFilter\BaseInputFilter;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Validator\Digits;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\NumberComparison;
use LaminasTest\InputFilter\TestAsset\InputFilterInterfaceStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Traversable;

use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 */
#[CoversClass(CollectionInputFilter::class)]
class CollectionInputFilterTest extends TestCase
{
    private CollectionInputFilter $inputFilter;

    protected function setUp(): void
    {
        $this->inputFilter = new CollectionInputFilter();
    }

    public function testSetInputFilterWithInvalidTypeThrowsInvalidArgumentException(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'expects an instance of Laminas\InputFilter\BaseInputFilter; received "stdClass"'
        );
        /** @psalm-suppress InvalidArgument */
        $inputFilter->setInputFilter(new stdClass());
    }

    /**
     * @param InputFilterSpecification|Traversable|BaseInputFilter $inputFilter
     * @param class-string $expectedType
     */
    #[DataProvider('inputFilterProvider')]
    public function testSetInputFilter(mixed $inputFilter, string $expectedType): void
    {
        $this->inputFilter->setInputFilter($inputFilter);

        self::assertInstanceOf($expectedType, $this->inputFilter->getInputFilter(), 'getInputFilter() type not match');
    }

    public function testGetDefaultInputFilter(): void
    {
        self::assertInstanceOf(BaseInputFilter::class, $this->inputFilter->getInputFilter());
    }

    #[DataProvider('isRequiredProvider')]
    public function testSetRequired(bool $value): void
    {
        $this->inputFilter->setIsRequired($value);
        self::assertEquals($value, $this->inputFilter->getIsRequired());
    }

    #[DataProvider('countVsDataProvider')]
    public function testSetCount(?int $count, ?array $data, int $expectedCount): void
    {
        if ($count !== null) {
            $this->inputFilter->setCount($count);
        }
        if ($data !== null) {
            $this->inputFilter->setData($data);
        }

        self::assertEquals($expectedCount, $this->inputFilter->getCount(), 'getCount() value not match');
    }

    public function testGetCountReturnsRightCountOnConsecutiveCallsWithDifferentData(): void
    {
        $collectionData1 = [
            ['foo' => 'bar'],
            ['foo' => 'baz'],
        ];

        $collectionData2 = [
            ['foo' => 'bar'],
        ];

        $this->inputFilter->setData($collectionData1);
        self::assertEquals(2, $this->inputFilter->getCount());
        $this->inputFilter->setData($collectionData2);
        self::assertEquals(1, $this->inputFilter->getCount());
    }

    #[DataProvider('dataVsValidProvider')]
    public function testDataVsValid(
        bool $required,
        ?int $count,
        array $data,
        BaseInputFilter $inputFilter,
        array $expectedRaw,
        array $expectedValues,
        bool $expectedValid,
        array $expectedMessages
    ): void {
        $this->inputFilter->setInputFilter($inputFilter);
        $this->inputFilter->setData($data);
        if ($count !== null) {
            $this->inputFilter->setCount($count);
        }
        $this->inputFilter->setIsRequired($required);

        self::assertEquals(
            $expectedValid,
            $this->inputFilter->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->inputFilter->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals($expectedRaw, $this->inputFilter->getRawValues(), 'getRawValues() value not match');
        self::assertEquals($expectedValues, $this->inputFilter->getValues(), 'getValues() value not match');
        self::assertEquals($expectedMessages, $this->inputFilter->getMessages(), 'getMessages() value not match');
    }

    /**
     * @psalm-return array<string, array{
     *     0: bool,
     *     1: null|int,
     *     2: array,
     *     3: BaseInputFilter&MockObject,
     *     4: array,
     *     5: array,
     *     6: bool,
     *     7: array
     * }>
     */
    public static function dataVsValidProvider(): array
    {
        $dataRaw      = [
            'fooInput' => 'fooRaw',
        ];
        $dataFiltered = [
            'fooInput' => 'fooFiltered',
        ];
        $colRaw       = [$dataRaw];
        $colFiltered  = [$dataFiltered];
        $errorMessage = [
            'fooInput' => 'fooError',
        ];
        $colMessages  = [$errorMessage];

        $invalidIF  = fn(): BaseInputFilter =>
            new InputFilterInterfaceStub(false, $dataRaw, $dataFiltered, $errorMessage);
        $validIF    = fn(): BaseInputFilter =>
            new InputFilterInterfaceStub(true, $dataRaw, $dataFiltered);
        $noValidIF  = fn(): BaseInputFilter =>
            new InputFilterInterfaceStub(null, $dataRaw, $dataFiltered);
        $isRequired = true;

        // @phpcs:disable Generic.Files.LineLength.TooLong,WebimpressCodingStandard.Arrays.Format.SingleLineSpaceBefore,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma
        return [
            // Description => [$required, $count, $data, $inputFilter, $expectedRaw, $expectedValues, $expectedValid, $expectedMessages]
            'Required: T, Count: N, Valid: T'  => [  $isRequired, null, $colRaw, $validIF()  , $colRaw, $colFiltered, true , []],
            'Required: T, Count: N, Valid: F'  => [  $isRequired, null, $colRaw, $invalidIF(), $colRaw, $colFiltered, false, $colMessages],
            'Required: T, Count: +1, Valid: F' => [  $isRequired,    2, $colRaw, $invalidIF(), $colRaw, $colFiltered, false, $colMessages],
            'Required: F, Count: N, Valid: T'  => [! $isRequired, null, $colRaw, $validIF()  , $colRaw, $colFiltered, true , []],
            'Required: F, Count: N, Valid: F'  => [! $isRequired, null, $colRaw, $invalidIF(), $colRaw, $colFiltered, false, $colMessages],
            'Required: F, Count: +1, Valid: F' => [! $isRequired,    2, $colRaw, $invalidIF(), $colRaw, $colFiltered, false, $colMessages],
            'Required: T, Data: [], Valid: X'  => [  $isRequired, null, []     , $noValidIF(), []     , []          , false, [['isEmpty' => 'Value is required and can\'t be empty']]],
            'Required: F, Data: [], Valid: X'  => [! $isRequired, null, []     , $noValidIF(), []     , []          , true , []],
        ];
        // @phpcs:enable Generic.Files.LineLength.TooLong,WebimpressCodingStandard.Arrays.Format.SingleLineSpaceBefore,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma
    }

    public function testSetValidationGroupUsingFormStyle(): void
    {
        $validationGroup    = [
            'fooGroup',
        ];
        $colValidationGroup = [$validationGroup];

        $dataRaw         = [
            'fooInput' => 'fooRaw',
        ];
        $dataFiltered    = [
            'fooInput' => 'fooFiltered',
        ];
        $colRaw          = [$dataRaw];
        $colFiltered     = [$dataFiltered];
        $baseInputFilter = $this->createBaseInputFilterMock(true, $dataRaw, $dataFiltered);
        $baseInputFilter->expects(self::once())
            ->method('setValidationGroup')
            ->with($validationGroup);

        $this->inputFilter->setInputFilter($baseInputFilter);
        $this->inputFilter->setData($colRaw);
        $this->inputFilter->setValidationGroup($colValidationGroup);

        self::assertTrue(
            $this->inputFilter->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->inputFilter->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals($colRaw, $this->inputFilter->getRawValues(), 'getRawValues() value not match');
        self::assertEquals($colFiltered, $this->inputFilter->getValues(), 'getValues() value not match');
        self::assertEquals([], $this->inputFilter->getMessages(), 'getMessages() value not match');
    }

    /** @psalm-return array<string, array{count: null|int, isValid: bool}> */
    public static function dataNestingCollection(): array
    {
        return [
            'count not specified' => [
                'count'   => null,
                'isValid' => true,
            ],
            'count=0'             => [
                'count'   => 0,
                'isValid' => true,
            ],
            'count = 1'           => [
                'count'   => 1,
                'isValid' => true,
            ],
            'count = 2'           => [
                'count'   => 2,
                'isValid' => false,
            ],
            'count = 3'           => [
                'count'   => 3,
                'isValid' => false,
            ],
        ];
    }

    #[DataProvider('dataNestingCollection')]
    public function testNestingCollectionCountCached(?int $count, bool $isValid): void
    {
        $firstInputFilter = new InputFilter();

        $firstCollection = new CollectionInputFilter();
        $firstCollection->setInputFilter($firstInputFilter);

        $someInput         = new Input('input');
        $secondInputFilter = new InputFilter();
        $secondInputFilter->add($someInput, 'input');

        $secondCollection = new CollectionInputFilter();
        $secondCollection->setInputFilter($secondInputFilter);
        if (null !== $count) {
            $secondCollection->setCount($count);
        }

        $firstInputFilter->add($secondCollection, 'second_collection');

        $mainInputFilter = new InputFilter();
        $mainInputFilter->add($firstCollection, 'first_collection');

        $data = [
            'first_collection' => [
                [
                    'second_collection' => [
                        [
                            'input' => 'some value',
                        ],
                        [
                            'input' => 'some value',
                        ],
                    ],
                ],
                [
                    'second_collection' => [
                        [
                            'input' => 'some value',
                        ],
                    ],
                ],
            ],
        ];

        $mainInputFilter->setData($data);
        self::assertSame($isValid, $mainInputFilter->isValid());
    }

    /**
     * @psalm-return array<string, array{
     *     0: InputFilterSpecification|Traversable|BaseInputFilter,
     *     1: class-string<InputFilterInterface>
     * }>
     */
    public static function inputFilterProvider(): array
    {
        $baseInputFilter = new BaseInputFilter();

        $inputFilterSpecificationAsArray = [];
        $inputSpecificationAsTraversable = new ArrayIterator($inputFilterSpecificationAsArray);

        $inputFilterSpecificationResult = new InputFilter();
        $inputFilterSpecificationResult->getFactory()->getInputFilterManager();

        return [
            // Description => [inputFilter, $expectedType]
            'BaseInputFilter' => [$baseInputFilter, BaseInputFilter::class],
            'array'           => [$inputFilterSpecificationAsArray, InputFilter::class],
            'Traversable'     => [$inputSpecificationAsTraversable, InputFilter::class],
        ];
    }

    /**
     * @psalm-return array<string, array{
     *     0: null|int,
     *     1: null|list<array<string, string>>,
     *     2: int
     * }>
     */
    public static function countVsDataProvider(): array
    {
        $data0 = [];
        $data1 = [['A' => 'a']];
        $data2 = [['A' => 'a'], ['B' => 'b']];
        // @codingStandardsIgnoreStart
        // phpcs:disable
        return [
            // Description => [$count, $data, $expectedCount]
            'C:   -1, D: null' => [  -1, null  ,  0],
            'C:    0, D: null' => [   0, null  ,  0],
            'C:    1, D: null' => [   1, null  ,  1],
            'C: null, D:    0' => [null, $data0,  0],
            'C: null, D:    1' => [null, $data1,  1],
            'C: null, D:    2' => [null, $data2,  2],
            'C:   -1, D:    0' => [  -1, $data0,  0],
            'C:    0, D:    0' => [   0, $data0,  0],
            'C:    1, D:    0' => [   1, $data0,  1],
            'C:   -1, D:    1' => [  -1, $data1,  0],
            'C:    0, D:    1' => [   0, $data1,  0],
            'C:    1, D:    1' => [   1, $data1,  1],
        ];
        // phpcs:enable
    }

    /** @psalm-return array<string, array{0: bool}> */
    public static function isRequiredProvider(): array
    {
        return [
            'enabled'  => [true],
            'disabled' => [false],
        ];
    }

    /**
     * @param array<string, mixed> $getRawValues
     * @param array<string, mixed> $getValues
     * @param string[] $getMessages
     * @return MockObject&BaseInputFilter
     */
    protected function createBaseInputFilterMock(
        ?bool $isValid = null,
        array $getRawValues = [],
        array $getValues = [],
        array $getMessages = []
    ) {
        /** @var BaseInputFilter&MockObject $inputFilter */
        $inputFilter = $this->createMock(BaseInputFilter::class);
        $inputFilter->method('getRawValues')
            ->willReturn($getRawValues);
        $inputFilter->method('getValues')
            ->willReturn($getValues);
        if (($isValid === false) || ($isValid === true)) {
            $inputFilter->expects(self::once())
                ->method('isValid')
                ->willReturn($isValid);
        } else {
            $inputFilter->expects(self::never())
                ->method('isValid');
        }
        $inputFilter->method('getMessages')
            ->willReturn($getMessages);

        return $inputFilter;
    }

    public function testGetUnknownWhenDataAreNotProvidedThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);

        $this->inputFilter->getUnknown();
    }

    public function testGetUnknownWhenAllFieldsAreKnownReturnsAnEmptyArray(): void
    {
        $inputFilter = new InputFilter();
        $inputFilter->add([
            'name' => 'foo',
        ]);

        $collectionInputFilter = $this->inputFilter;
        $collectionInputFilter->setInputFilter($inputFilter);

        $collectionInputFilter->setData([
            ['foo' => 'bar'],
            ['foo' => 'baz'],
        ]);

        $unknown = $collectionInputFilter->getUnknown();

        self::assertFalse($collectionInputFilter->hasUnknown());
        self::assertCount(0, $unknown);
    }

    public function testGetUnknownFieldIsUnknown(): void
    {
        $inputFilter = new InputFilter();
        $inputFilter->add([
            'name' => 'foo',
        ]);

        $collectionInputFilter = $this->inputFilter;
        $collectionInputFilter->setInputFilter($inputFilter);

        $collectionInputFilter->setData([
            ['foo' => 'bar', 'baz' => 'hey'],
            ['foo' => 'car', 'tor' => 'ver'],
        ]);

        $unknown = $collectionInputFilter->getUnknown();

        self::assertTrue($collectionInputFilter->hasUnknown());
        self::assertEquals([['baz' => 'hey'], ['tor' => 'ver']], $unknown);
    }

    /** @psalm-return array<string, array{0: array}> */
    public static function invalidCollections(): array
    {
        return [
            'null'       => [[['this' => 'is valid'], null]],
            'false'      => [[['this' => 'is valid'], false]],
            'true'       => [[['this' => 'is valid'], true]],
            'zero'       => [[['this' => 'is valid'], 0]],
            'int'        => [[['this' => 'is valid'], 1]],
            'zero-float' => [[['this' => 'is valid'], 0.0]],
            'float'      => [[['this' => 'is valid'], 1.1]],
            'string'     => [[['this' => 'is valid'], 'this is not']],
            'object'     => [[['this' => 'is valid'], (object) ['this' => 'is invalid']]],
        ];
    }

    #[DataProvider('invalidCollections')]
    public function testSettingDataAsArrayWithInvalidCollectionsRaisesException(array $data): void
    {
        $collectionInputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid item in collection');
        $collectionInputFilter->setData($data);
    }

    #[DataProvider('invalidCollections')]
    public function testSettingDataAsTraversableWithInvalidCollectionsRaisesException(array $data): void
    {
        $collectionInputFilter = $this->inputFilter;
        $data                  = new ArrayIterator($data);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid item in collection');
        $collectionInputFilter->setData($data);
    }

    /** @psalm-return array<string, array{0: mixed}> */
    public static function invalidDataType(): array
    {
        return [
            'null'       => [null],
            'false'      => [false],
            'true'       => [true],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['this is not'],
            'object'     => [(object) ['this' => 'is invalid']],
        ];
    }

    #[DataProvider('invalidDataType')]
    public function testSettingDataWithNonArrayNonTraversableRaisesException(mixed $data): void
    {
        $collectionInputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid collection');
        /** @psalm-suppress MixedArgument */
        $collectionInputFilter->setData($data);
    }

    public function testCollectionValidationDoesNotReuseMessagesBetweenInputs(): void
    {
        $inputFilter = new InputFilter();
        $inputFilter->add([
            'name'       => 'phone',
            'required'   => true,
            'validators' => [
                ['name' => Digits::class],
                ['name' => NotEmpty::class],
            ],
        ]);
        $inputFilter->add([
            'name'       => 'name',
            'required'   => true,
            'validators' => [
                ['name' => NotEmpty::class],
            ],
        ]);

        $collectionInputFilter = $this->inputFilter;
        $collectionInputFilter->setInputFilter($inputFilter);

        $collectionInputFilter->setData([
            [
                'name' => 'Tom',
            ],
            [
                'phone' => 'tom@tom',
                'name'  => 'Tom',
            ],
        ]);

        $isValid  = $collectionInputFilter->isValid();
        $messages = $collectionInputFilter->getMessages();

        // @codingStandardsIgnoreStart
        self::assertFalse($isValid);
        self::assertCount(2, $messages);

        self::assertArrayHasKey('phone', $messages[0]);
        self::assertCount(1, $messages[0]['phone']);
        self::assertContains('Value is required and can\'t be empty', $messages[0]['phone']);

        self::assertArrayHasKey('phone', $messages[1]);
        self::assertCount(1, $messages[1]['phone']);
        self::assertNotContains('Value is required and can\'t be empty', $messages[1]['phone']);
        self::assertContains('The input must contain only digits', $messages[1]['phone']);
        // @codingStandardsIgnoreEnd
    }

    public function testCollectionValidationUsesCustomInputErrorMessages(): void
    {
        $inputFilter = new InputFilter();
        $inputFilter->add([
            'name'          => 'phone',
            'required'      => true,
            'validators'    => [
                ['name' => Digits::class],
                ['name' => NotEmpty::class],
            ],
            'error_message' => 'CUSTOM ERROR MESSAGE',
        ]);
        $inputFilter->add([
            'name'       => 'name',
            'required'   => true,
            'validators' => [
                ['name' => NotEmpty::class],
            ],
        ]);

        $collectionInputFilter = $this->inputFilter;
        $collectionInputFilter->setInputFilter($inputFilter);

        $collectionInputFilter->setData([
            [
                'name' => 'Tom',
            ],
            [
                'phone' => 'tom@tom',
                'name'  => 'Tom',
            ],
        ]);

        $isValid  = $collectionInputFilter->isValid();
        $messages = $collectionInputFilter->getMessages();

        self::assertFalse($isValid);
        self::assertCount(2, $messages);

        self::assertArrayHasKey('phone', $messages[0]);
        self::assertCount(1, $messages[0]['phone']);
        self::assertContains('CUSTOM ERROR MESSAGE', $messages[0]['phone']);
        self::assertNotContains('Value is required and can\'t be empty', $messages[0]['phone']);

        self::assertArrayHasKey('phone', $messages[1]);
        self::assertCount(1, $messages[1]['phone']);
        self::assertContains('CUSTOM ERROR MESSAGE', $messages[1]['phone']);
    }

    public function testDuplicatedErrorMessages(): void
    {
        $factory     = new Factory();
        $inputFilter = $factory->createInputFilter(
            [
                'element' => [
                    'type'  => InputFilter::class,
                    'type1' => [
                        'type'         => CollectionInputFilter::class,
                        'input_filter' => [
                            'test_field' => [
                                'type'         => CollectionInputFilter::class,
                                'input_filter' => [
                                    'test_field1' => [
                                        'required'   => false,
                                        'validators' => [
                                            [
                                                'name'    => NumberComparison::class,
                                                'options' => [
                                                    'min'     => 50,
                                                    'max'     => 100,
                                                    'message' => '%value% is incorrect',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'price'       => [
                                        'required'   => false,
                                        'validators' => [
                                            [
                                                'name'    => NumberComparison::class,
                                                'options' => [
                                                    'min'     => 50,
                                                    'max'     => 100,
                                                    'message' => '%value% is incorrect',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $inputFilter->setData(
            [
                'element' => [
                    'type1' => [
                        [
                            'test_field' => [
                                [
                                    'test_field1' => -20,
                                    'price'       => 20,
                                ],
                                [
                                    'test_field1' => -15,
                                    'price'       => 15,
                                ],
                                [
                                    'test_field1' => -10,
                                    'price'       => 10,
                                ],
                            ],
                        ],
                        [
                            'test_field' => [
                                [
                                    'test_field1' => -5,
                                    'price'       => 5,
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
        self::assertFalse($inputFilter->isValid());
        self::assertEquals([
            'element' => [
                'type1' => [
                    [
                        'test_field' => [
                            [
                                'test_field1' => [
                                    NumberComparison::ERROR_NOT_GREATER_INCLUSIVE => '-20 is incorrect',
                                ],
                                'price'       => [
                                    NumberComparison::ERROR_NOT_GREATER_INCLUSIVE => '20 is incorrect',
                                ],
                            ],
                            [
                                'test_field1' => [
                                    NumberComparison::ERROR_NOT_GREATER_INCLUSIVE => '-15 is incorrect',
                                ],
                                'price'       => [
                                    NumberComparison::ERROR_NOT_GREATER_INCLUSIVE => '15 is incorrect',
                                ],
                            ],
                            [
                                'test_field1' => [
                                    NumberComparison::ERROR_NOT_GREATER_INCLUSIVE => '-10 is incorrect',
                                ],
                                'price'       => [
                                    NumberComparison::ERROR_NOT_GREATER_INCLUSIVE => '10 is incorrect',
                                ],
                            ],
                        ],
                    ],
                    [
                        'test_field' => [
                            [
                                'test_field1' => [
                                    NumberComparison::ERROR_NOT_GREATER_INCLUSIVE => '-5 is incorrect',
                                ],
                                'price'       => [
                                    NumberComparison::ERROR_NOT_GREATER_INCLUSIVE => '5 is incorrect',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $inputFilter->getMessages());
    }

    public function testLazyLoadsANotEmptyValidatorWhenNoneProvided(): void
    {
        self::assertInstanceOf(NotEmpty::class, $this->inputFilter->getNotEmptyValidator());
    }

    public function testAllowsComposingANotEmptyValidator(): void
    {
        $notEmptyValidator = new NotEmpty();
        $this->inputFilter->setNotEmptyValidator($notEmptyValidator);
        self::assertSame($notEmptyValidator, $this->inputFilter->getNotEmptyValidator());
    }

    public function testUsesMessageFromComposedNotEmptyValidatorWhenRequiredButCollectionIsEmpty(): void
    {
        $message           = 'this is the validation message';
        $notEmptyValidator = new NotEmpty();
        $notEmptyValidator->setMessage($message);

        $this->inputFilter->setIsRequired(true);
        $this->inputFilter->setNotEmptyValidator($notEmptyValidator);

        $this->inputFilter->setData([]);

        self::assertFalse($this->inputFilter->isValid());

        self::assertEquals([
            [NotEmpty::IS_EMPTY => $message],
        ], $this->inputFilter->getMessages());
    }

    public function testSetDataUsingSetDataAndRunningIsValidReturningSameAsOriginalForUnfilteredData(): void
    {
        $filteredArray = [
            [
                'bar' => 'foo',
                'foo' => 'bar',
            ],
        ];

        $unfilteredArray = [
            ...$filteredArray,
            ...[
                [
                    'foo' => 'bar',
                ],
            ],
        ];

        $baseInputFilter = (new BaseInputFilter())
            ->add(new Input(), 'bar');

        $collectionInputFilter = (new CollectionInputFilter())->setInputFilter($baseInputFilter);
        $collectionInputFilter->setData($unfilteredArray);

        $collectionInputFilter->isValid();

        self::assertSame($unfilteredArray, $collectionInputFilter->getUnfilteredData());
    }

    /**
     * @return array<string, array{0: array, 1: null|array, 2: null|array}>
     */
    public static function contextProvider(): array
    {
        $data = ['fooInput' => 'fooValue'];

        return [
            // Description => [$data, $customContext, $expectedContext]
            'null context'        => [[$data], null, null],
            'array context'       => [[$data], [$data], [$data]],
            'traversable context' => [[$data], [new ArrayObject($data)], [new ArrayObject($data)]],
            'empty data'          => [[], ['fooContext'], ['fooContext']],
        ];
    }

    #[DataProvider('contextProvider')]
    public function testValidationContext(array $data, ?array $customContext, ?array $expectedContext): void
    {
        $baseInputFilter = $this->createMock(BaseInputFilter::class);
        $baseInputFilter->expects(self::exactly(count($data)))
            ->method('isValid')
            ->with($expectedContext)
            ->willReturn(true);

        $collectionInputFilter = (new CollectionInputFilter())->setInputFilter($baseInputFilter);
        $collectionInputFilter->setData($data);

        self::assertTrue(
            $collectionInputFilter->isValid($customContext),
            'isValid() value not match. Detail: ' . json_encode(
                $collectionInputFilter->getMessages(),
                JSON_THROW_ON_ERROR
            )
        );
    }
}
