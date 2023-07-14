<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps

namespace LaminasTest\InputFilter;

use ArrayIterator;
use ArrayObject;
use FilterIterator;
use Laminas\InputFilter\BaseInputFilter;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputInterface;
use Laminas\InputFilter\UnfilteredDataInterface;
use LaminasTest\InputFilter\TestAsset\InputFilterInterfaceStub;
use LaminasTest\InputFilter\TestAsset\InputInterfaceStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use stdClass;

use function array_keys;
use function array_merge;
use function array_walk;
use function count;
use function in_array;
use function is_callable;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

#[CoversClass(BaseInputFilter::class)]
class BaseInputFilterTest extends TestCase
{
    /** @var BaseInputFilter */
    protected $inputFilter;

    protected function setUp(): void
    {
        $this->inputFilter = new BaseInputFilter();
    }

    public function testInputFilterIsEmptyByDefault(): void
    {
        $filter = $this->inputFilter;
        self::assertCount(0, $filter);
    }

    public function testAddWithInvalidInputTypeThrowsInvalidArgumentException(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'expects an instance of Laminas\InputFilter\InputInterface or Laminas\InputFilter\InputFilterInterface '
            . 'as its first argument; received "stdClass"'
        );
        /** @psalm-suppress InvalidArgument */
        $inputFilter->add(new stdClass());
    }

    public function testGetThrowExceptionIfInputDoesNotExists(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no input found matching "not exists"');
        $inputFilter->get('not exists');
    }

    public function testReplaceWithInvalidInputTypeThrowsInvalidArgumentException(): void
    {
        $inputFilter = $this->inputFilter;
        $inputFilter->add(new Input('foo'), 'replace_me');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'expects an instance of Laminas\InputFilter\InputInterface or Laminas\InputFilter\InputFilterInterface '
            . 'as its first argument; received "stdClass"'
        );
        $inputFilter->replace(new stdClass(), 'replace_me');
    }

    public function testReplaceThrowExceptionIfInputToReplaceDoesNotExists(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no input found matching "not exists"');
        $inputFilter->replace(new Input('foo'), 'not exists');
    }

    public function testGetValueThrowExceptionIfInputDoesNotExists(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"not exists" was not found in the filter');
        $inputFilter->getValue('not exists');
    }

    public function testGetRawValueThrowExceptionIfInputDoesNotExists(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"not exists" was not found in the filter');
        $inputFilter->getRawValue('not exists');
    }

    public function testSetDataWithInvalidDataTypeThrowsInvalidArgumentException(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expects an array or Traversable argument; received stdClass');
        /** @psalm-suppress InvalidArgument */
        $inputFilter->setData(new stdClass());
    }

    public function testIsValidThrowExceptionIfDataWasNotSetYet(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no data present to validate');
        $inputFilter->isValid();
    }

    public function testSetValidationGroupSkipsRecursionWhenInputIsNotAnInputFilter(): void
    {
        $inputFilter = $this->inputFilter;

        /** @var InputInterface&MockObject $nestedInput */
        $nestedInput = $this->createMock(InputInterface::class);
        $inputFilter->add($nestedInput, 'fooInput');

        $inputFilter->setValidationGroup(['fooInput' => 'foo']);

        $r = new ReflectionObject($inputFilter);
        $p = $r->getProperty('validationGroup');
        self::assertEquals(['fooInput'], $p->getValue($inputFilter));
    }

    public function testSetValidationGroupAllowsSpecifyingArrayOfInputsToNestedInputFilter(): void
    {
        $inputFilter = $this->inputFilter;

        $nestedInputFilter = new BaseInputFilter();

        /** @var InputInterface&MockObject $nestedInput1 */
        $nestedInput1 = $this->createMock(InputInterface::class);
        $nestedInputFilter->add($nestedInput1, 'nested-input1');

        /** @var InputInterface&MockObject $nestedInput2 */
        $nestedInput2 = $this->createMock(InputInterface::class);
        $nestedInputFilter->add($nestedInput2, 'nested-input2');

        $inputFilter->add($nestedInputFilter, 'nested');

        $inputFilter->setValidationGroup(['nested' => ['nested-input1', 'nested-input2']]);

        $r = new ReflectionObject($inputFilter);
        $p = $r->getProperty('validationGroup');
        self::assertEquals(['nested'], $p->getValue($inputFilter));
        self::assertEquals(['nested-input1', 'nested-input2'], $p->getValue($nestedInputFilter));
    }

    public function testSetValidationGroupThrowExceptionIfInputFilterNotExists(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'expects a list of valid input names; "anotherNotExistsInputFilter" was not found'
        );
        $inputFilter->setValidationGroup(['notExistInputFilter' => 'anotherNotExistsInputFilter']);
    }

    public function testSetValidationGroupThrowExceptionIfInputFilterInArgumentListNotExists(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'expects a list of valid input names; "notExistInputFilter" was not found'
        );
        $inputFilter->setValidationGroup('notExistInputFilter');
    }

    public function testHasUnknownThrowExceptionIfDataWasNotSetYet(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(RuntimeException::class);
        $inputFilter->hasUnknown();
    }

    public function testGetUnknownThrowExceptionIfDataWasNotSetYet(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(RuntimeException::class);
        $inputFilter->getUnknown();
    }

    /**
     * Verify the state of the input filter is the desired after change it using the method `add()`
     */
    #[DataProvider('addMethodArgumentsProvider')]
    public function testAddHasGet(
        InputInterface|InputFilterInterface|iterable $input,
        ?string $name,
        ?string $expectedInputName,
        object $expectedInput
    ): void {
        $inputFilter = $this->inputFilter;
        self::assertFalse(
            $inputFilter->has($expectedInputName),
            "InputFilter shouldn't have an input with the name $expectedInputName yet"
        );
        $currentNumberOfFilters = count($inputFilter);

        $return = $inputFilter->add($input, $name);
        self::assertSame($inputFilter, $return, "add() must return it self");

        // **Check input collection state**
        self::assertTrue($inputFilter->has($expectedInputName), "There is no input with name $expectedInputName");
        self::assertCount($currentNumberOfFilters + 1, $inputFilter, 'Number of filters must be increased by 1');

        $returnInput = $inputFilter->get($expectedInputName);
        self::assertEquals($expectedInput, $returnInput, 'get() does not match the expected input');
    }

    /**
     * Verify the state of the input filter is the desired after change it using the method `add()` and `remove()`
     */
    #[DataProvider('addMethodArgumentsProvider')]
    public function testAddRemove(
        InputInterface|InputFilterInterface|iterable $input,
        ?string $name,
        ?string $expectedInputName
    ): void {
        $inputFilter = $this->inputFilter;

        $inputFilter->add($input, $name);
        $currentNumberOfFilters = count($inputFilter);

        $return = $inputFilter->remove($expectedInputName);
        self::assertSame($inputFilter, $return, 'remove() must return it self');

        self::assertFalse($inputFilter->has($expectedInputName), "There is no input with name $expectedInputName");
        self::assertCount($currentNumberOfFilters - 1, $inputFilter, 'Number of filters must be decreased by 1');
    }

    public function testAddingInputWithNameDoesNotInjectNameInInput(): void
    {
        $inputFilter = $this->inputFilter;

        $foo = new Input('foo');
        $inputFilter->add($foo, 'bas');

        $test = $inputFilter->get('bas');
        self::assertSame($foo, $test, 'get() does not match the input added');
        self::assertEquals('foo', $foo->getName(), 'Input name should not change');
    }

    #[DataProvider('inputProvider')]
    public function testReplace(
        InputInterface|InputFilterInterface|iterable $input,
        ?string $inputName,
        object $expectedInput
    ): void {
        $inputFilter    = $this->inputFilter;
        $nameToReplace  = 'replace_me';
        $inputToReplace = new Input($nameToReplace);

        $inputFilter->add($inputToReplace);
        $currentNumberOfFilters = count($inputFilter);

        $return = $inputFilter->replace($input, $nameToReplace);
        self::assertSame($inputFilter, $return, 'replace() must return it self');
        self::assertCount($currentNumberOfFilters, $inputFilter, "Number of filters shouldn't change");

        $returnInput = $inputFilter->get($nameToReplace);
        self::assertEquals($expectedInput, $returnInput, 'get() does not match the expected input');
    }

    /**
     * @param array<string, InputInterface|InputFilterInterface|iterable> $inputs
     * @param iterable<mixed> $data
     * @param array<string, mixed> $expectedRawValues
     * @param array<string, mixed> $expectedValues
     * @param list<InputInterface> $expectedInvalidInputs
     * @param list<InputInterface> $expectedValidInputs
     * @param string[] $expectedMessages
     */
    #[DataProvider('setDataArgumentsProvider')]
    public function testSetDataAndGetRawValueGetValue(
        array $inputs,
        iterable $data,
        array $expectedRawValues,
        array $expectedValues,
        bool $expectedIsValid,
        array $expectedInvalidInputs,
        array $expectedValidInputs,
        array $expectedMessages
    ): void {
        $inputFilter = $this->inputFilter;
        foreach ($inputs as $inputName => $input) {
            $inputFilter->add($input, $inputName);
        }
        $return = $inputFilter->setData($data);
        self::assertSame($inputFilter, $return, 'setData() must return it self');

        // ** Check filter state **
        self::assertSame($expectedRawValues, $inputFilter->getRawValues(), 'getRawValues() value not match');
        foreach ($expectedRawValues as $inputName => $expectedRawValue) {
            self::assertSame(
                $expectedRawValue,
                $inputFilter->getRawValue($inputName),
                'getRawValue() value not match for input ' . $inputName
            );
        }

        self::assertSame($expectedValues, $inputFilter->getValues(), 'getValues() value not match');
        foreach ($expectedValues as $inputName => $expectedValue) {
            self::assertSame(
                $expectedValue,
                $inputFilter->getValue($inputName),
                'getValue() value not match for input ' . $inputName
            );
        }

        // ** Check validation state **
        // phpcs:disable Generic.Files.LineLength.TooLong
        self::assertEquals($expectedIsValid, $inputFilter->isValid(), 'isValid() value not match');
        self::assertEquals($expectedInvalidInputs, $inputFilter->getInvalidInput(), 'getInvalidInput() value not match');
        self::assertEquals($expectedValidInputs, $inputFilter->getValidInput(), 'getValidInput() value not match');
        self::assertEquals($expectedMessages, $inputFilter->getMessages(), 'getMessages() value not match');
        // phpcs:enable Generic.Files.LineLength.TooLong

        // ** Check unknown fields **
        self::assertFalse($inputFilter->hasUnknown(), 'hasUnknown() value not match');
        self::assertEmpty($inputFilter->getUnknown(), 'getUnknown() value not match');
    }

    /**
     * @param array<string, InputInterface|InputFilterInterface|iterable> $inputs
     * @param iterable<mixed> $data
     * @param array<string, mixed> $expectedRawValues
     * @param array<string, mixed> $expectedValues
     * @param list<InputInterface> $expectedInvalidInputs
     * @param list<InputInterface> $expectedValidInputs
     * @param string[] $expectedMessages
     */
    #[DataProvider('setDataArgumentsProvider')]
    public function testSetTraversableDataAndGetRawValueGetValue(
        array $inputs,
        iterable $data,
        array $expectedRawValues,
        array $expectedValues,
        bool $expectedIsValid,
        array $expectedInvalidInputs,
        array $expectedValidInputs,
        array $expectedMessages
    ): void {
        $dataTypes = $this->dataTypes();
        $this->testSetDataAndGetRawValueGetValue(
            $inputs,
            $dataTypes['Traversable']($data),
            $expectedRawValues,
            $expectedValues,
            $expectedIsValid,
            $expectedInvalidInputs,
            $expectedValidInputs,
            $expectedMessages
        );
    }

    public function testResetEmptyValidationGroupRecursively(): void
    {
        $data         = [
            'flat' => 'foo',
            'deep' => [
                'deep-input1' => 'deep-foo1',
                'deep-input2' => 'deep-foo2',
            ],
        ];
        $expectedData = array_merge($data, ['notSet' => null]);
        /** @var Input&MockObject $flatInput */
        $flatInput = $this->getMockBuilder(Input::class)
            ->enableProxyingToOriginalMethods()
            ->setConstructorArgs(['flat'])
            ->getMock();
        $flatInput->expects(self::once())
            ->method('setValue')
            ->with('foo');
        // Inputs without value must be reset for to have clean states when use different setData arguments
        /** @var Input&MockObject $resetInput */
        $resetInput = $this->getMockBuilder(Input::class)
            ->enableProxyingToOriginalMethods()
            ->setConstructorArgs(['notSet'])
            ->getMock();
        $resetInput->expects(self::once())
            ->method('resetValue');

        $filter = $this->inputFilter;
        $filter->add($flatInput);
        $filter->add($resetInput);
        $deepInputFilter = new BaseInputFilter();
        $deepInputFilter->add(new Input(), 'deep-input1');
        $deepInputFilter->add(new Input(), 'deep-input2');
        $filter->add($deepInputFilter, 'deep');
        $filter->setData($data);
        $filter->setValidationGroup(['deep' => 'deep-input1']);
        // reset validation group
        $filter->setValidationGroup(InputFilterInterface::VALIDATE_ALL);
        self::assertEquals($expectedData, $filter->getValues());
    }

    /*
     * Idea for this one is that validation may need to rely on context -- e.g., a "password confirmation"
     * field may need to know what the original password entered was in order to compare.
     */

    /**
     * @psalm-return array<string, array{
     *     0: iterable<array-key, mixed>,
     *     1: null|string,
     *     2: array<string, string>|string
     * }>
     */
    public static function contextProvider(): array
    {
        $data             = ['fooInput' => 'fooValue'];
        $traversableData  = new ArrayObject(['fooInput' => 'fooValue']);
        $expectedFromData = ['fooInput' => 'fooValue'];

        return [
            // Description => [$data, $customContext, $expectedContext]
            'by default get context from data (array)'       => [$data, null, $expectedFromData],
            'by default get context from data (Traversable)' => [$traversableData, null, $expectedFromData],
            'use custom context'                             => [[], 'fooContext', 'fooContext'],
        ];
    }

    /**
     * @param iterable<array-key, mixed> $data
     * @param string|array<string, string> $expectedContext
     */
    #[DataProvider('contextProvider')]
    public function testValidationContext($data, ?string $customContext, $expectedContext): void
    {
        $filter = $this->inputFilter;

        $input = self::createInputInterfaceMock('fooInput', true, true, $expectedContext);
        $filter->add($input, 'fooInput');

        $filter->setData($data);

        self::assertTrue(
            $filter->isValid($customContext),
            'isValid() value not match. Detail: ' . json_encode($filter->getMessages(), JSON_THROW_ON_ERROR)
        );
    }

    public function testBuildValidationContextUsingInputGetRawValue(): void
    {
        $data            = [];
        $expectedContext = ['fooInput' => 'fooRawValue'];
        $filter          = $this->inputFilter;

        $input = self::createInputInterfaceMock('fooInput', true, true, $expectedContext, 'fooRawValue');
        $filter->add($input, 'fooInput');

        $filter->setData($data);

        self::assertTrue(
            $filter->isValid(),
            'isValid() value not match. Detail: ' . json_encode($filter->getMessages(), JSON_THROW_ON_ERROR)
        );
    }

    public function testContextIsTheSameWhenARequiredInputIsGivenAndOptionalInputIsMissing(): void
    {
        $data            = [
            'inputRequired' => 'inputRequiredValue',
        ];
        $expectedContext = [
            'inputRequired' => 'inputRequiredValue',
            'inputOptional' => null,
        ];
        $inputRequired   = self::createInputInterfaceMock('fooInput', true, true, $expectedContext);
        $inputOptional   = self::createInputInterfaceMock('fooInput', false);

        $filter = $this->inputFilter;
        $filter->add($inputRequired, 'inputRequired');
        $filter->add($inputOptional, 'inputOptional');

        $filter->setData($data);

        self::assertTrue(
            $filter->isValid(),
            'isValid() value not match. Detail: ' . json_encode($filter->getMessages(), JSON_THROW_ON_ERROR)
        );
    }

    public function testValidationSkipsFieldsMarkedNotRequiredWhenNoDataPresent(): void
    {
        $filter = $this->inputFilter;

        $optionalInputName = 'fooOptionalInput';
        /** @var InputInterface&MockObject $optionalInput */
        $optionalInput = $this->createMock(InputInterface::class);
        $optionalInput->method('getName')
            ->willReturn($optionalInputName);
        $optionalInput->expects(self::never())
            ->method('isValid');
        $data = [];

        $filter->add($optionalInput);

        $filter->setData($data);

        self::assertTrue(
            $filter->isValid(),
            'isValid() value not match. Detail . ' . json_encode($filter->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertArrayNotHasKey(
            $optionalInputName,
            $filter->getValidInput(),
            'Missing optional fields must not appear as valid input neither invalid input'
        );
        self::assertArrayNotHasKey(
            $optionalInputName,
            $filter->getInvalidInput(),
            'Missing optional fields must not appear as valid input neither invalid input'
        );
    }

    #[DataProvider('unknownScenariosProvider')]
    public function testUnknown(array $inputs, array $data, bool $hasUnknown, array $getUnknown): void
    {
        $inputFilter = $this->inputFilter;
        foreach ($inputs as $name => $input) {
            $inputFilter->add($input, $name);
        }

        $inputFilter->setData($data);

        self::assertEquals($getUnknown, $inputFilter->getUnknown(), 'getUnknown() value not match');
        self::assertEquals($hasUnknown, $inputFilter->hasUnknown(), 'hasUnknown() value not match');
    }

    public function testGetInputs(): void
    {
        $filter = $this->inputFilter;

        $foo = new Input('foo');
        $bar = new Input('bar');

        $filter->add($foo);
        $filter->add($bar);

        $filters = $filter->getInputs();

        self::assertCount(2, $filters);
        self::assertEquals('foo', $filters['foo']->getName());
        self::assertEquals('bar', $filters['bar']->getName());
    }

    public function testAddingExistingInputWillMergeIntoExisting(): void
    {
        $filter = $this->inputFilter;

        $foo1 = new Input('foo');
        $foo1->setRequired(true);
        $filter->add($foo1);

        $foo2 = new Input('foo');
        $foo2->setRequired(false);
        $filter->add($foo2);

        self::assertFalse($filter->get('foo')->isRequired());
    }

    public function testAddingAnInputFilterWithTheSameNameAsTheInputWillReplace(): void
    {
        $input  = new Input('a');
        $filter = new InputFilter();

        $this->inputFilter->add($input);

        self::assertSame($input, $this->inputFilter->get('a'));

        $this->inputFilter->add($filter, 'a');

        self::assertSame($filter, $this->inputFilter->get('a'));
    }

    public function testMerge(): void
    {
        $inputFilter       = $this->inputFilter;
        $originInputFilter = new BaseInputFilter();

        $inputFilter->add(new Input(), 'foo');
        $inputFilter->add(new Input(), 'bar');

        $originInputFilter->add(new Input(), 'baz');

        $inputFilter->merge($originInputFilter);

        self::assertEquals(
            [
                'foo',
                'bar',
                'baz',
            ],
            array_keys($inputFilter->getInputs())
        );
    }

    public function testNestedInputFilterShouldAllowNonArrayValueForData(): void
    {
        $filter1      = new BaseInputFilter();
        $nestedFilter = new BaseInputFilter();
        $nestedFilter->add(new Input('nestedField1'));
        $filter1->add($nestedFilter, 'nested');

        // non scalar and non null value
        $filter1->setData(['nested' => false]);
        self::assertNull($filter1->getValues()['nested']['nestedField1']);

        $filter1->setData(['nested' => 123]);
        self::assertNull($filter1->getValues()['nested']['nestedField1']);

        $filter1->setData(['nested' => new stdClass()]);
        self::assertNull($filter1->getValues()['nested']['nestedField1']);
    }

    public function testInstanceOfUnfilteredDataInterface(): void
    {
        $baseInputFilter = new BaseInputFilter();

        self::assertInstanceOf(
            UnfilteredDataInterface::class,
            $baseInputFilter,
            sprintf('%s should implement %s', BaseInputFilter::class, UnfilteredDataInterface::class)
        );
    }

    public function testGetUnfilteredDataReturnsArray(): void
    {
        $baseInputFilter = new BaseInputFilter();

        self::assertIsArray($baseInputFilter->getUnfilteredData());
    }

    public function testSetUnfilteredDataReturnsBaseInputFilter(): void
    {
        $baseInputFilter = new BaseInputFilter();

        self::assertInstanceOf(BaseInputFilter::class, $baseInputFilter->setUnfilteredData([]));
    }

    public function testSettingAndReturningDataArrayUnfilteredDataInterface(): void
    {
        $testArray = [
            'foo' => 'bar',
        ];

        $baseInputFilter = new BaseInputFilter();
        $baseInputFilter->setUnfilteredData($testArray);

        self::assertSame($testArray, $baseInputFilter->getUnfilteredData());
    }

    public function testSettingAndReturnDataArrayUsingSetDataForUnfilteredDataInterface(): void
    {
        $testArray = [
            'foo' => 'bar',
        ];

        $baseInputFilter = new BaseInputFilter();
        $baseInputFilter->setData($testArray);

        self::assertSame($testArray, $baseInputFilter->getUnfilteredData());
    }

    public function testSetDataUsingSetDataAndApplyFiltersReturningSameAsOriginalForUnfilteredData(): void
    {
        $filteredArray = [
            'bar' => 'foo',
        ];

        $unfilteredArray = array_merge(
            $filteredArray,
            [
                'foo' => 'bar',
            ]
        );

        /** @var BaseInputFilter $baseInputFilter */
        $baseInputFilter = (new BaseInputFilter())
            ->add(new Input(), 'bar')
            ->setData($unfilteredArray);

        self::assertSame($unfilteredArray, $baseInputFilter->getUnfilteredData());
        self::assertSame($filteredArray, $baseInputFilter->getValues());
        self::assertSame($filteredArray, $baseInputFilter->getRawValues());
    }

    /**
     * @psalm-return array<string, array{
     *     0: InputInterface,
     *     1: null|string,
     *     2: null|string,
     *     3: InputInterface,
     * }>
     */
    public static function addMethodArgumentsProvider(): array
    {
        $inputTypes = static::inputProvider();

        $inputName = static fn($inputTypeData) => $inputTypeData[1];

        $sameInput = static fn($inputTypeData) => $inputTypeData[2];

        // phpcs:disable WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma
        $dataTemplates = [
            // Description => [[$input argument], $name argument, $expectedName, $expectedInput]
            'null'        => [$inputTypes, null         , $inputName   , $sameInput],
            'custom_name' => [$inputTypes, 'custom_name', 'custom_name', $sameInput],
        ];
        // phpcs:enable WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma

        // Expand data template matrix for each possible input type.
        // Description => [$input argument, $name argument, $expectedName, $expectedInput]
        $dataSets = [];
        foreach ($dataTemplates as $dataTemplateDescription => $dataTemplate) {
            foreach ($dataTemplate[0] as $inputTypeDescription => $inputTypeData) {
                $tmpTemplate    = $dataTemplate;
                $tmpTemplate[0] = $inputTypeData[0]; // expand input
                if (is_callable($dataTemplate[2])) {
                    $tmpTemplate[2] = $dataTemplate[2]($inputTypeData);
                }
                $tmpTemplate[3] = $dataTemplate[3]($inputTypeData);

                $dataSets[$inputTypeDescription . ' / ' . $dataTemplateDescription] = $tmpTemplate;
            }
        }

        return $dataSets;
    }

    /**
     * @psalm-return array<string, array{
     *     0: array<string, InputInterface|InputFilterInterface|iterable>,
     *     1: iterable<mixed>,
     *     2: array<string, mixed>,
     *     3: array<string, mixed>,
     *     4: bool,
     *     5: list<InputInterface>,
     *     6: list<InputInterface>,
     *     7: string[]
     * }>
     */
    public static function setDataArgumentsProvider(): array
    {
        $iAName    = 'InputA';
        $iBName    = 'InputB';
        $vRaw      = 'rawValue';
        $vFiltered = 'filteredValue';

        $dARaw        = [$iAName => $vRaw];
        $dBRaw        = [$iBName => $vRaw];
        $dAfRaw       = [$iAName => ['fooInput' => $vRaw]];
        $d2Raw        = array_merge($dARaw, $dBRaw);
        $dAfBRaw      = array_merge($dAfRaw, $dBRaw);
        $dAFiltered   = [$iAName => $vFiltered];
        $dBFiltered   = [$iBName => $vFiltered];
        $dAfFiltered  = [$iAName => ['fooInput' => $vFiltered]];
        $d2Filtered   = array_merge($dAFiltered, $dBFiltered);
        $dAfBFiltered = array_merge($dAfFiltered, $dBFiltered);

        $required = true;
        $valid    = true;
        $bOnFail  = true;

        /**
         * @param array<string, string> $msg
         * @return callable(): InputInterface
         */
        $input = function (
            string $iName,
            bool $required,
            bool $bOnFail,
            bool $isValid,
            array $msg = []
        ) use (
            $vRaw,
            $vFiltered
        ): callable {
            return fn(array|null|string $context): InputInterface => self::createInputInterfaceMock(
                $iName,
                $required,
                $isValid,
                $context,
                $vRaw,
                $vFiltered,
                $msg,
                $bOnFail
            );
        };

        $inputFilter = fn(bool $isValid, array $msg = []): callable =>
            function () use ($isValid, $vRaw, $vFiltered, $msg): InputFilterInterface {
                $vRaw      = ['fooInput' => $vRaw];
                $vFiltered = ['fooInput' => $vFiltered];
                return BaseInputFilterTest::createInputFilterInterfaceMock($isValid, $vRaw, $vFiltered, $msg);
            };

        // phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSame,Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpacingAfterComma
        $iAri      = [$iAName => $input($iAName, $required, ! $bOnFail, ! $valid, ['Invalid ' . $iAName])];
        $iAriX     = [$iAName => $input($iAName, $required, $bOnFail,   ! $valid, ['Invalid ' . $iAName])];
        $iArvX     = [$iAName => $input($iAName, $required, $bOnFail,   $valid,   [])];
        $iBri      = [$iBName => $input($iBName, $required, ! $bOnFail, ! $valid, ['Invalid ' . $iBName])];
        $iBriX     = [$iBName => $input($iBName, $required, $bOnFail,   ! $valid, ['Invalid ' . $iBName])];
        $iBrvX     = [$iBName => $input($iBName, $required, $bOnFail,   $valid,   [])];
        $ifAi      = [$iAName => $inputFilter(! $valid, ['fooInput' => ['Invalid ' . $iAName]])];
        $ifAv      = [$iAName => $inputFilter($valid)];
        $iAriBri   = array_merge($iAri,  $iBri);
        $iArvXBrvX = array_merge($iArvX, $iBrvX);
        $iAriBrvX  = array_merge($iAri,  $iBrvX);
        $iArvXBir  = array_merge($iArvX, $iBri);
        $iAriXBrvX = array_merge($iAriX, $iBrvX);
        $iArvXBriX = array_merge($iArvX, $iBriX);
        $iAriXBriX = array_merge($iAriX, $iBriX);
        $ifAiBri   = array_merge($ifAi, $iBri);
        $ifAiBrvX  = array_merge($ifAi, $iBrvX);
        $ifAvBri   = array_merge($ifAv, $iBri);
        $ifAvBrv   = array_merge($ifAv, $iBrvX);

        $msgAInv   = [$iAName => ['Invalid InputA']];
        $msgBInv   = [$iBName => ['Invalid InputB']];
        $msgAfInv  = [$iAName => ['fooInput' => ['Invalid InputA']]];
        $msg2Inv   = array_merge($msgAInv, $msgBInv);
        $msgAfBInv = array_merge($msgAfInv, $msgBInv);
        // phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSame,Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpacingAfterComma

        // phpcs:disable Generic.Files.LineLength.TooLong,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpacingAfterComma
        $dataSets = [
            // Description              => [$inputs, $data argument, $expectedRawValues, $expectedValues, $expectedIsValid,  $expectedInvalidInputs, $expectedValidInputs, $expectedMessages]
            'invalid Break invalid'     => [$iAriXBriX,   $d2Raw,   $d2Raw,   $d2Filtered, false,     $iAri,         [],   $msgAInv],
            'invalid Break valid'       => [$iAriXBrvX,   $d2Raw,   $d2Raw,   $d2Filtered, false,     $iAri,         [],   $msgAInv],
            'valid   Break invalid'     => [$iArvXBriX,   $d2Raw,   $d2Raw,   $d2Filtered, false,     $iBri,      $iAri,   $msgBInv],
            'valid   Break valid'       => [$iArvXBrvX,   $d2Raw,   $d2Raw,   $d2Filtered,  true,        [], $iArvXBrvX,         []],
            'valid   invalid'           => [$iArvXBir,    $d2Raw,   $d2Raw,   $d2Filtered, false,     $iBri,     $iArvX,   $msgBInv],
            'IInvalid IValid'           => [$iAriBrvX,    $d2Raw,   $d2Raw,   $d2Filtered, false,     $iAri,     $iBrvX,   $msgAInv],
            'IInvalid IInvalid'         => [$iAriBri,     $d2Raw,   $d2Raw,   $d2Filtered, false,  $iAriBri,         [],   $msg2Inv],
            'IInvalid IValid / Partial' => [$iAriBri,     $dARaw,   $d2Raw,   $d2Filtered, false, $iAriBrvX,         [],   $msg2Inv],
            'IFInvalid IValid'          => [$ifAiBrvX,  $dAfBRaw, $dAfBRaw, $dAfBFiltered, false,     $ifAi,     $iBrvX,  $msgAfInv],
            'IFInvalid IInvalid'        => [$ifAiBri,   $dAfBRaw, $dAfBRaw, $dAfBFiltered, false,  $ifAiBri,         [], $msgAfBInv],
            'IFValid IInvalid'          => [$ifAvBri,   $dAfBRaw, $dAfBRaw, $dAfBFiltered, false,     $iBri,      $ifAv,   $msgBInv],
            'IFValid IValid'            => [$ifAvBrv,   $dAfBRaw, $dAfBRaw, $dAfBFiltered,  true,        [],   $ifAvBrv,         []],
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong,WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpacingAfterComma

        array_walk(
            $dataSets,
            static function (array &$set): void {
                // Create unique mock input instances for each set
                foreach ($set[0] as $name => $createMock) {
                    self::assertIsString($name);
                    self::assertIsCallable($createMock);
                    $input = $createMock($set[2]);

                    $set[0][$name] = $input;
                    if (in_array($name, array_keys($set[5]))) {
                        $set[5][$name] = $input;
                    }
                    if (in_array($name, array_keys($set[6]))) {
                        $set[6][$name] = $input;
                    }
                }
            }
        );

        return $dataSets;
    }

    /**
     * @psalm-return array<string, array{
     *     0: list<InputInterface>,
     *     1: array<string, string>,
     *     2: bool,
     *     3: array<string, string>
     * }>
     */
    public static function unknownScenariosProvider(): array
    {
        $inputA          = self::createInputInterfaceMock('inputA', true);
        $dataA           = ['inputA' => 'foo'];
        $dataUnknown     = ['inputUnknown' => 'unknownValue'];
        $dataAAndUnknown = array_merge($dataA, $dataUnknown);

        // phpcs:disable WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma
        return [
            // Description           => [$inputs, $data, $hasUnknown, $getUnknown]
            'empty data and inputs'  => [[]       , []              , false, []],
            'empty data'             => [[$inputA], []              , false, []],
            'data and fields match'  => [[$inputA], $dataA          , false, []],
            'data known and unknown' => [[$inputA], $dataAAndUnknown, true , $dataUnknown],
            'data unknown'           => [[$inputA], $dataUnknown    , true , $dataUnknown],
            'data unknown, no input' => [[]       , $dataUnknown    , true , $dataUnknown],
        ];
        // phpcs:enable WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma
    }

    /**
     * @psalm-return array<string, array{
     *     0: InputInterface|InputFilterInterface,
     *     1: null|string,
     *     2: InputInterface|InputFilterInterface,
     * }>
     */
    public static function inputProvider(): array
    {
        $input       = self::createInputInterfaceMock('fooInput', null);
        $inputFilter = self::createInputFilterInterfaceMock();

        // phpcs:disable WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma
        return [
            // Description         => [input,     expected name, $expectedReturnInput]
            'InputInterface'       => [$input,       'fooInput',       $input],
            'InputFilterInterface' => [$inputFilter,       null, $inputFilter],
        ];
        // phpcs:enable WebimpressCodingStandard.WhiteSpace.CommaSpacing.SpaceBeforeComma
    }

    /**
     * @param array<string, mixed> $getRawValues
     * @param array<string, mixed> $getValues
     * @param array<array-key, array<string, string>> $getMessages
     */
    private static function createInputFilterInterfaceMock(
        bool|null $isValid = null,
        array $getRawValues = [],
        array $getValues = [],
        array $getMessages = []
    ): InputFilterInterfaceStub {
        return new InputFilterInterfaceStub($isValid, $getRawValues, $getValues, $getMessages);
    }

    /** @param array<string, string> $getMessages */
    private static function createInputInterfaceMock(
        string $name,
        bool|null $isRequired,
        bool|null $isValid = null,
        array|string|null $context = null,
        mixed $getRawValue = null,
        mixed $getValue = null,
        array $getMessages = [],
        bool $breakOnFailure = false
    ): InputInterfaceStub {
        return new InputInterfaceStub(
            $name,
            $isRequired,
            $isValid,
            $context,
            $getRawValue,
            $getValue,
            $getMessages,
            $breakOnFailure,
        );
    }

    /**
     * @return callable[]
     */
    protected function dataTypes(): array
    {
        return [
            // Description => callable
            'array'       => static fn(array $data): array => $data,
            'Traversable' => fn(array $data) => $this->getMockBuilder(FilterIterator::class)
                ->setConstructorArgs([new ArrayIterator($data)])
                ->getMock(),
        ];
    }
}
