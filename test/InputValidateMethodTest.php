<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\StringTrim;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputInterface;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Regex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @psalm-import-type InputSpecification from InputFilterInterface */
final class InputValidateMethodTest extends TestCase
{
    /** @return iterable<string, array{0: InputSpecification, 1: mixed, 2: bool}> */
    public static function validateDataProviderWithEmptyValues(): iterable
    {
        foreach (['Empty String' => '', 'null' => null, 'Empty Array' => []] as $valueKey => $value) {
            yield from [
                'Req: T, Allow F, Cont: F: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => true,
                        'allow_empty'       => false,
                        'continue_if_empty' => false,
                    ],
                    $value,
                    false,
                ],
                'Req: T, Allow T, Cont: F: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => true,
                        'allow_empty'       => true,
                        'continue_if_empty' => false,
                    ],
                    $value,
                    true,
                ],
                'Req: T, Allow T, Cont: T: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => true,
                        'allow_empty'       => true,
                        'continue_if_empty' => true,
                    ],
                    $value,
                    false,
                ],
                'Req: F, Allow T, Cont: T: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => false,
                        'allow_empty'       => true,
                        'continue_if_empty' => true,
                    ],
                    $value,
                    false,
                ],
                'Req: F, Allow F, Cont: T: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => false,
                        'allow_empty'       => false,
                        'continue_if_empty' => true,
                    ],
                    $value,
                    false,
                ],
                'Req: F, Allow F, Cont: F: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => false,
                        'allow_empty'       => false,
                        'continue_if_empty' => false,
                    ],
                    $value,
                    true,
                ],
            ];
        }
    }

    /** @return iterable<string, array{0: InputSpecification, 1: mixed, 2: bool}> */
    public static function validateDataProviderWithNonEmptyValues(): iterable
    {
        $nonEmptyValues = [
            'Non-Empty String' => 'Kermit',
            'Int 0'            => 0,
            'Int 1'            => 1,
            'Float 0.0'        => 0.0,
            'Float 1.0'        => 1.0,
            'Zero String'      => '0',
            'Non Empty Array'  => ['Miss', 'Piggy'],
        ];

        foreach ($nonEmptyValues as $valueKey => $value) {
            yield from [
                'Req: T, Allow F, Cont: F: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => true,
                        'allow_empty'       => false,
                        'continue_if_empty' => false,
                    ],
                    $value,
                    true,
                ],
                'Req: T, Allow T, Cont: F: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => true,
                        'allow_empty'       => true,
                        'continue_if_empty' => false,
                    ],
                    $value,
                    true,
                ],
                'Req: T, Allow T, Cont: T: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => true,
                        'allow_empty'       => true,
                        'continue_if_empty' => true,
                    ],
                    $value,
                    true,
                ],
                'Req: F, Allow T, Cont: T: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => false,
                        'allow_empty'       => true,
                        'continue_if_empty' => true,
                    ],
                    $value,
                    true,
                ],
                'Req: F, Allow F, Cont: T: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => false,
                        'allow_empty'       => false,
                        'continue_if_empty' => true,
                    ],
                    $value,
                    true,
                ],
                'Req: F, Allow F, Cont: F: (' . $valueKey . ')' => [
                    [
                        'name'              => 'foo',
                        'required'          => false,
                        'allow_empty'       => false,
                        'continue_if_empty' => false,
                    ],
                    $value,
                    true,
                ],
            ];
        }
    }

    /** @param InputSpecification $spec */
    #[DataProvider('validateDataProviderWithEmptyValues')]
    #[DataProvider('validateDataProviderWithNonEmptyValues')]
    public function testValidateWithEmptyValues(array $spec, mixed $inputData, bool $valid): void
    {
        $factory = TestHelper::getContainer()->get(Factory::class);
        $input   = $factory->createInput($spec);

        $name = $spec['name'] ?? null;
        self::assertIsString($name);
        $result = $input->validate($inputData, [$name => $inputData]);

        self::assertSame($valid, $result->valid());
        self::assertSame($inputData, $result->rawValue(), 'Raw value should be identical to the input');
        self::assertSame($inputData, $result->value(), 'Filtered value should be identical to the input');
        $expectedErrorCount = $valid ? 0 : 1;
        self::assertCount($expectedErrorCount, $result->getMessages());
        self::assertSame($name, $result->name());
    }

    public function testEmptyValidationFailureMessageIsPresentWhenEmptyValuesPassValidation(): void
    {
        $factory = TestHelper::getContainer()->get(Factory::class);
        $input   = $factory->createInput([
            'name'              => 'Gonzo',
            'required'          => false,
            'allow_empty'       => true,
            'continue_if_empty' => true,
        ]);

        $result = $input->validate('', ['Gonzo' => '']);
        self::assertFalse($result->valid());
        self::assertCount(1, $result->getMessages());
        self::assertArrayHasKey(InputInterface::EMPTY_FAILURE_VALIDATION_KEY, $result->getMessages()->toArray());
        self::assertSame(
            'The value for "Gonzo" was empty, but its configuration prohibits an empty value. '
            . 'Prepend a "NotEmpty" validator to this input’s chain in order to customise '
            . 'this validation failure message',
            $result->getMessages()[InputInterface::EMPTY_FAILURE_VALIDATION_KEY],
        );
    }

    public function testEmptyValidationFailureMessageIsNotPresentWhenNotEmptyValidatorIsPresent(): void
    {
        $factory = TestHelper::getContainer()->get(Factory::class);
        $input   = $factory->createInput([
            'name'              => 'Gonzo',
            'required'          => false,
            'allow_empty'       => true,
            'continue_if_empty' => true,
            'validators'        => [
                ['name' => NotEmpty::class],
            ],
        ]);

        $result = $input->validate('', ['Gonzo' => '']);
        self::assertFalse($result->valid());
        self::assertCount(1, $result->getMessages());
        self::assertArrayHasKey(NotEmpty::IS_EMPTY, $result->getMessages()->toArray());
        self::assertArrayNotHasKey(InputInterface::EMPTY_FAILURE_VALIDATION_KEY, $result->getMessages()->toArray());
    }

    public function testFiltersAreAppliedToTheInput(): void
    {
        $factory = TestHelper::getContainer()->get(Factory::class);
        $input   = $factory->createInput([
            'name'              => 'foo',
            'required'          => true,
            'allow_empty'       => false,
            'continue_if_empty' => false,
            'filters'           => [
                ['name' => StringTrim::class],
            ],
        ]);

        $result = $input->validate(' fred ', ['foo' => ' fred ']);
        self::assertTrue($result->valid());
        self::assertSame(' fred ', $result->rawValue());
        self::assertSame('fred', $result->value());
        self::assertCount(0, $result->getMessages());
    }

    public function testFallbackValueIsFilteredWhenGiven(): void
    {
        $factory = TestHelper::getContainer()->get(Factory::class);
        $input   = $factory->createInput([
            'name'              => 'foo',
            'required'          => true,
            'allow_empty'       => false,
            'continue_if_empty' => true,
            'fallback_value'    => ' Marge ',
            'filters'           => [
                ['name' => StringTrim::class],
            ],
        ]);

        $result = $input->validate('', ['foo' => '']);
        self::assertTrue($result->valid());
        self::assertSame('', $result->rawValue());
        self::assertSame('Marge', $result->value());
        self::assertCount(0, $result->getMessages());
    }

    public function testFallbackValueIsNotValidatedWhenGiven(): void
    {
        $factory = TestHelper::getContainer()->get(Factory::class);
        $input   = $factory->createInput([
            'name'              => 'foo',
            'required'          => true,
            'allow_empty'       => false,
            'continue_if_empty' => true,
            'fallback_value'    => 'Marge',
            'validators'        => [
                [
                    'name'    => Regex::class,
                    'options' => [
                        'pattern' => '/^[0-9]+$/',
                    ],
                ],
            ],
        ]);

        $result = $input->validate('', ['foo' => '']);
        self::assertTrue($result->valid());
        self::assertSame('', $result->rawValue());
        self::assertSame('Marge', $result->value());
        self::assertCount(0, $result->getMessages());
    }

    public function testAnyFailingValidationDoesNotTriggerBuiltInEmptyChecks(): void
    {
        $factory = TestHelper::getContainer()->get(Factory::class);
        $input   = $factory->createInput([
            'name'              => 'foo',
            'required'          => true,
            'allow_empty'       => false,
            'continue_if_empty' => true,
            'validators'        => [
                [
                    'name'    => Regex::class,
                    'options' => [
                        'pattern' => '/^[0-9]+$/',
                    ],
                ],
            ],
        ]);

        $result = $input->validate('foo', ['foo' => 'foo']);
        self::assertFalse($result->valid());
        self::assertSame('foo', $result->rawValue());
        self::assertSame('foo', $result->value());
        self::assertCount(1, $result->getMessages());
        self::assertArrayHasKey(Regex::NOT_MATCH, $result->getMessages()->toArray());
    }

    public function testValidateMethodDoesNotAffectInternals(): void
    {
        $factory = TestHelper::getContainer()->get(Factory::class);
        $input   = $factory->createInput([
            'name'              => 'foo',
            'required'          => true,
            'allow_empty'       => false,
            'continue_if_empty' => true,
            'validators'        => [
                [
                    'name'    => Regex::class,
                    'options' => [
                        'pattern' => '/^[0-9]+$/',
                    ],
                ],
            ],
        ]);
        self::assertInstanceOf(Input::class, $input);

        $result = $input->validate('foo', ['foo' => 'foo']);
        self::assertFalse($result->valid());
        $this->assertInternalsAreNotMutated($input);

        $result = $input->validate('123', ['foo' => '123']);
        self::assertTrue($result->valid());
        $this->assertInternalsAreNotMutated($input);
    }

    private function assertInternalsAreNotMutated(Input $input): void
    {
        self::assertNull($input->getValue());
        self::assertNull($input->getRawValue());
        self::assertFalse($input->hasValue());
    }
}
