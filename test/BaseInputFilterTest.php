<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName


declare(strict_types=1);

namespace LaminasTest\InputFilter;

use ArrayIterator;
use ArrayObject;
use Closure;
use Laminas\InputFilter\BaseInputFilter;
use Laminas\InputFilter\Exception\InputNotFoundException;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputInterface;
use Laminas\InputFilter\UnfilteredDataInterface;
use LaminasTest\InputFilter\TestAsset\InputFilterInterfaceStub;
use LaminasTest\InputFilter\TestAsset\InputInterfaceStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_keys;
use function array_merge;
use function array_walk;
use function in_array;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * @psalm-import-type InputSpecification from InputFilterInterface
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 */
#[CoversClass(BaseInputFilter::class)]
final class BaseInputFilterTest extends TestCase
{
    protected Factory $factory;
    protected BaseInputFilter $inputFilter;

    protected function setUp(): void
    {
        $this->factory = TestHelper::createInputFilterFactory();

        $this->inputFilter = new BaseInputFilter($this->factory);
    }

    private function createInput(?string $name = null): Input
    {
        return new Input(TestHelper::createFilterChain(), TestHelper::createValidatorChain(), $name);
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

        $this->expectException(InputNotFoundException::class);
        $this->expectExceptionMessage('"not exists"');
        $inputFilter->getRawValue('not exists');
    }

    public function testIsValidThrowExceptionIfDataWasNotSetYet(): void
    {
        $inputFilter = $this->inputFilter;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no data present to validate');
        $inputFilter->isValid();
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
     * @param array<string, InputInterface|InputFilterInterface> $inputs
     * @param iterable<array-key, mixed> $data
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
        array $expectedMessages,
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
                'getRawValue() value not match for input ' . $inputName,
            );
        }

        self::assertSame($expectedValues, $inputFilter->getValues(), 'getValues() value not match');
        foreach ($expectedValues as $inputName => $expectedValue) {
            self::assertSame(
                $expectedValue,
                $inputFilter->getValue($inputName),
                'getValue() value not match for input ' . $inputName,
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
     * @param array<string, InputInterface|InputFilterInterface> $inputs
     * @param iterable<array-key, mixed> $data
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
        array $expectedMessages,
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
            $expectedMessages,
        );
    }

    /*
     * Idea for this one is that validation may need to rely on context -- e.g., a "password confirmation"
     * field may need to know what the original password entered was in order to compare.
     */

    /**
     * @psalm-return array<string, array{
     *     0: iterable<array-key, mixed>,
     *     1: null|array<array-key, mixed>,
     *     2: array<array-key, mixed>,
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
            'use custom context'                             => [[], ['fooContext'], ['fooContext']],
        ];
    }

    /**
     * @param iterable<array-key, mixed> $data
     * @param array<array-key, mixed> $expectedContext
     */
    #[DataProvider('contextProvider')]
    public function testValidationContext(
        iterable $data,
        array|null $customContext,
        array $expectedContext,
    ): void {
        $filter = $this->inputFilter;

        $input = self::createInputInterfaceMock('fooInput', true, true, $expectedContext);
        $filter->add($input, 'fooInput');

        $filter->setData($data);

        self::assertTrue(
            $filter->isValid($customContext),
            'isValid() value not match. Detail: ' . json_encode($filter->getMessages(), JSON_THROW_ON_ERROR),
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
            'isValid() value not match. Detail: ' . json_encode($filter->getMessages(), JSON_THROW_ON_ERROR),
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
            'isValid() value not match. Detail: ' . json_encode($filter->getMessages(), JSON_THROW_ON_ERROR),
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
            'isValid() value not match. Detail . ' . json_encode($filter->getMessages(), JSON_THROW_ON_ERROR),
        );
        self::assertArrayNotHasKey(
            $optionalInputName,
            $filter->getValidInput(),
            'Missing optional fields must not appear as valid input neither invalid input',
        );
        self::assertArrayNotHasKey(
            $optionalInputName,
            $filter->getInvalidInput(),
            'Missing optional fields must not appear as valid input neither invalid input',
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

    public function testNestedInputFilterShouldAllowNonArrayValueForData(): void
    {
        /** @psalm-var BaseInputFilter<array{nested: array{nestedField1: mixed}}> $filter1 */
        $filter1      = new BaseInputFilter($this->factory);
        $nestedFilter = new BaseInputFilter($this->factory);
        $nestedFilter->add(
            $this->createInput('nestedField1'),
        );
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
        $baseInputFilter = new BaseInputFilter($this->factory);

        self::assertInstanceOf(
            UnfilteredDataInterface::class,
            $baseInputFilter,
            sprintf('%s should implement %s', BaseInputFilter::class, UnfilteredDataInterface::class),
        );
    }

    public function testGetUnfilteredDataReturnsArray(): void
    {
        $baseInputFilter = new BaseInputFilter($this->factory);

        self::assertIsArray($baseInputFilter->getUnfilteredData());
    }

    public function testSetUnfilteredDataReturnsBaseInputFilter(): void
    {
        $baseInputFilter = new BaseInputFilter($this->factory);

        self::assertInstanceOf(BaseInputFilter::class, $baseInputFilter->setUnfilteredData([]));
    }

    public function testSettingAndReturningDataArrayUnfilteredDataInterface(): void
    {
        $testArray = [
            'foo' => 'bar',
        ];

        $baseInputFilter = new BaseInputFilter($this->factory);
        $baseInputFilter->setUnfilteredData($testArray);

        self::assertSame($testArray, $baseInputFilter->getUnfilteredData());
    }

    public function testSettingAndReturnDataArrayUsingSetDataForUnfilteredDataInterface(): void
    {
        $testArray = [
            'foo' => 'bar',
        ];

        $baseInputFilter = new BaseInputFilter($this->factory);
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
            ],
        );

        /** @var BaseInputFilter $baseInputFilter */
        $baseInputFilter = (new BaseInputFilter($this->factory))
            ->add($this->createInput(), 'bar')
            ->setData($unfilteredArray);

        self::assertSame($unfilteredArray, $baseInputFilter->getUnfilteredData());
        self::assertSame($filteredArray, $baseInputFilter->getValues());
        self::assertSame($filteredArray, $baseInputFilter->getRawValues());
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
        $input = fn(string $iName, bool $required, bool $bOnFail, bool $isValid, array $msg = []): callable =>
            fn(array|null|string $context): InputInterface => self::createInputInterfaceMock(
                $iName,
                $required,
                $isValid,
                $context,
                $vRaw,
                $vFiltered,
                $msg,
                $bOnFail,
            );

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
            },
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
     * @param array<string, mixed> $getRawValues
     * @param array<string, mixed> $getValues
     * @param array<array-key, array<string, string>> $getMessages
     */
    public static function createInputFilterInterfaceMock(
        bool|null $isValid = null,
        array $getRawValues = [],
        array $getValues = [],
        array $getMessages = [],
    ): InputFilterInterfaceStub {
        return new InputFilterInterfaceStub(
            TestHelper::createInputFilterFactory(),
            $isValid,
            $getRawValues,
            $getValues,
            $getMessages,
        );
    }

    /** @param array<string, string> $getMessages */
    public static function createInputInterfaceMock(
        string $name,
        bool|null $isRequired,
        bool|null $isValid = null,
        array|string|null $context = null,
        mixed $getRawValue = null,
        mixed $getValue = null,
        array $getMessages = [],
        bool $breakOnFailure = false,
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
     * @return array{
     *     array: Closure(array): array<array-key, mixed>,
     *     Traversable: Closure(array): iterable<array-key, mixed>,
     * }
     */
    protected function dataTypes(): array
    {
        return [
            // Description => callable
            'array'       => static fn(array $data): array => $data,
            'Traversable' => fn(array $data): iterable => new ArrayIterator($data),
        ];
    }
}
