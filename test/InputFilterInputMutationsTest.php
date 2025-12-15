<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\Exception\InputNotFoundException;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use PHPUnit\Framework\TestCase;

/**
 * This test case covers the base input filter operations for:
 * - add()
 * - remove()
 * - merge()
 * - replace()
 * - has()
 * - get()
 * - getInputs()
 * - count()
 */
final class InputFilterInputMutationsTest extends TestCase
{
    private Factory $factory;

    protected function setUp(): void
    {
        $this->factory = TestHelper::createInputFilterFactory();
    }

    private function createEmptyInputFilter(): InputFilter
    {
        return new InputFilter($this->factory);
    }

    public function testInputFilterIsEmptyByDefault(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        self::assertCount(0, $inputFilter);
    }

    public function testGetIsExceptionalWhenTheInputDoesNotExist(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $this->expectException(InputNotFoundException::class);
        $inputFilter->get('not-there');
    }

    public function testAddedInputInstancesAreAccessible(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input       = $this->factory->createInput(['name' => 'fred']);

        $inputFilter->add($input);

        self::assertTrue($inputFilter->has('fred'));
        self::assertSame($input, $inputFilter->get('fred'));
    }

    public function testAddingInstanceWithDefinedNameOverridesInputName(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input       = $this->factory->createInput(['name' => 'fred']);

        $inputFilter->add($input, 'piglet');

        self::assertFalse($inputFilter->has('fred'));
        self::assertTrue($inputFilter->has('piglet'));
        self::assertSame($input, $inputFilter->get('piglet'));
    }

    public function testAddingViaSpec(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $inputFilter->add(['name' => 'fred']);

        self::assertTrue($inputFilter->has('fred'));
        self::assertInstanceOf(Input::class, $inputFilter->get('fred'));
    }

    public function testAddingViaSpecWithDefinedNameOverridesName(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $inputFilter->add(['name' => 'fred'], 'doughnut');

        self::assertFalse($inputFilter->has('fred'));
        self::assertTrue($inputFilter->has('doughnut'));
        $input = $inputFilter->get('doughnut');
        self::assertInstanceOf(Input::class, $input);
    }

    public function testAddingWithDefinedNameDoesNotChangeTheInputName(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $inputFilter->add(['name' => 'fred'], 'doughnut');

        $input = $inputFilter->get('doughnut');
        self::assertInstanceOf(Input::class, $input);

        self::assertSame('fred', $input->getName());
    }

    public function testInputsCanBeRemoved(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input       = $this->factory->createInput(['name' => 'fred']);
        $inputFilter->add($input);
        $inputFilter->remove('fred');
        self::assertFalse($inputFilter->has('fred'));
    }

    public function testInputsCanBeReplaced(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input1      = $this->factory->createInput(['name' => 'fred']);
        $input2      = $this->factory->createInput(['name' => 'fred']);

        $inputFilter->add($input1);
        $inputFilter->replace($input2, 'fred');

        self::assertTrue($inputFilter->has('fred'));
        self::assertSame($input2, $inputFilter->get('fred'));
    }

    public function testReplacementInputDoesNotAssumeReplacedName(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input1      = $this->factory->createInput(['name' => 'goat']);
        $input2      = $this->factory->createInput(['name' => 'donkey']);

        $inputFilter->add($input1);
        $inputFilter->replace($input2, 'goat');

        self::assertTrue($inputFilter->has('goat'));
        self::assertSame($input2, $inputFilter->get('goat'));
        self::assertSame('donkey', $input2->getName());
    }

    public function testAddHasFluentInterface(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        self::assertSame(
            $inputFilter,
            $inputFilter->add(['name' => 'ding-dong']),
        );
    }

    public function testRemoveHasFluentInterface(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $inputFilter->add(['name' => 'ding-dong']);
        self::assertSame(
            $inputFilter,
            $inputFilter->remove('ding-dong'),
        );
    }

    public function testYouCanRemoveInputsThatDontExist(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        self::assertFalse($inputFilter->has('ding-dong'));

        $inputFilter->remove('ding-dong');
    }

    public function testYouCantReplaceInputsThatDontExist(): void
    {
        $inputFilter = $this->createEmptyInputFilter();

        $this->expectException(InputNotFoundException::class);

        $inputFilter->replace(['name' => 'fred'], 'fred');
    }

    public function testReplaceHasFluentInterface(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input1      = $this->factory->createInput(['name' => 'goat']);
        $input2      = $this->factory->createInput(['name' => 'goat']);

        $inputFilter->add($input1);

        self::assertSame(
            $inputFilter,
            $inputFilter->replace($input2, 'goat'),
        );
    }

    public function testAddInputFilter(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $nested      = $this->createEmptyInputFilter();

        $inputFilter->add($nested, 'foo');

        self::assertSame($nested, $inputFilter->get('foo'));
    }

    public function testReplaceInputWithInputFilter(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input       = $this->factory->createInput(['name' => 'goat']);
        $inputFilter->add($input);

        $nested = $this->createEmptyInputFilter();

        $inputFilter->replace($nested, 'goat');

        self::assertSame($nested, $inputFilter->get('goat'));
    }

    public function testCountIncreasesOnlyWithTopLevelAdditions(): void
    {
        $inputFilter = $this->createEmptyInputFilter();

        self::assertCount(0, $inputFilter);

        $inputFilter->add($this->factory->createInput(['name' => 'goat']));

        self::assertCount(1, $inputFilter);

        $nestedInputFilter = $this->createEmptyInputFilter();
        $inputFilter->add($nestedInputFilter, 'donkey');

        self::assertCount(2, $inputFilter);

        $nestedInputFilter->add($this->factory->createInput(['name' => 'horse']));

        self::assertCount(2, $inputFilter);

        $inputFilter->remove('donkey');

        self::assertCount(1, $inputFilter);

        $inputFilter->remove('goat');

        self::assertCount(0, $inputFilter);
    }

    public function testYouCantAddANestedInputFilterWithoutSpecifyingAName(): void
    {
        $inputFilter       = $this->createEmptyInputFilter();
        $nestedInputFilter = $this->createEmptyInputFilter();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input or InputFilter name must be a non-empty string or an int, null given');

        $inputFilter->add($nestedInputFilter);
    }

    public function testYouCantAddAnInputWithoutSpecifyingAName(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input       = $this->factory->createInput([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input or InputFilter name must be a non-empty string or an int, null given');

        $inputFilter->add($input);
    }

    public function testAddingAnInputWithAnExistingNameWillCauseTheInputsToBeMerged(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input1      = $this->factory->createInput(['name' => 'fred']);
        $input2      = $this->factory->createInput([
            'name'    => 'fred',
            'filters' => [
                ['name' => StringTrim::class],
            ],
        ]);

        $filterChain1 = $input1->getFilterChain();
        self::assertInstanceOf(FilterChain::class, $filterChain1);
        self::assertCount(0, $filterChain1);

        $filterChain2 = $input2->getFilterChain();
        self::assertInstanceOf(FilterChain::class, $filterChain2);
        self::assertCount(1, $filterChain2);

        $inputFilter->add($input1);
        $inputFilter->add($input2);

        self::assertSame($input1, $inputFilter->get('fred'));

        self::assertCount(1, $inputFilter, 'There should only be 1 input still');
        self::assertCount(1, $filterChain1, 'The Filter chain for the existing input should have been mutated');
    }

    public function testExplicitCallToCountMethod(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        self::assertSame(0, $inputFilter->count());

        $inputFilter->add($this->factory->createInput(['name' => 'fred']));

        self::assertSame(1, $inputFilter->count());
    }

    public function testAddingAnInputFilterWithTheSameNameAsTheInputWillReplace(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input       = $this->factory->createInput(['name' => 'fred']);
        $inputFilter->add($input);

        $replacement = $this->createEmptyInputFilter();

        $inputFilter->add($replacement, 'fred');

        self::assertSame($replacement, $inputFilter->get('fred'));
    }

    public function testGetInputs(): void
    {
        $inputFilter = $this->createEmptyInputFilter();
        $input1      = $this->factory->createInput(['name' => 'foo']);
        $input2      = $this->factory->createInput(['name' => 'bar']);

        $inputFilter->add($input1);
        $inputFilter->add($input2);

        self::assertSame([
            'foo' => $input1,
            'bar' => $input2,
        ], $inputFilter->getInputs());
    }

    public function testMerge(): void
    {
        $original    = $this->createEmptyInputFilter();
        $replacement = $this->createEmptyInputFilter();

        $input1 = $this->factory->createInput(['name' => 'foo']);
        $input2 = $this->factory->createInput(['name' => 'bar']);
        $input3 = $this->factory->createInput(['name' => 'baz']);
        $input4 = $this->factory->createInput(['name' => 'bat']);

        $original->add($input1);
        $original->add($input2);

        $replacement->add($input3);
        $replacement->add($input4);

        $original->merge($replacement);

        self::assertSame([
            'foo' => $input1,
            'bar' => $input2,
            'baz' => $input3,
            'bat' => $input4,
        ], $original->getInputs());

        self::assertSame([
            'baz' => $input3,
            'bat' => $input4,
        ], $replacement->getInputs());
    }
}
