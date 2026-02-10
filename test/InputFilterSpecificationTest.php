<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterValidationResult;
use Laminas\InputFilter\ValidationResultInterface;
use LaminasTest\InputFilter\Spec\BasicFilterAndValidate;
use LaminasTest\InputFilter\Spec\Expectation;
use LaminasTest\InputFilter\Spec\InputFilterTestSpecInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_pop;
use function explode;
use function sprintf;

/** @psalm-import-type InputFilterSpecification from InputFilterInterface */
final class InputFilterSpecificationTest extends TestCase
{
    /** @return list<class-string<InputFilterTestSpecInterface>> */
    public static function specList(): array
    {
        return [
            BasicFilterAndValidate::class,
        ];
    }

    /** @return iterable<string, array{0: InputFilterSpecification, 1: Expectation}> */
    public static function specDataProvider(): iterable
    {
        foreach (self::specList() as $class) {
            $parts = explode('\\', $class);
            $name  = array_pop($parts);

            $spec = new $class();

            foreach ($spec->expectations() as $key => $expectation) {
                yield sprintf('%s - %s', $name, $key) => [
                    $spec->spec(),
                    $expectation,
                ];
            }
        }
    }

    /** @param InputFilterSpecification $spec */
    #[DataProvider('specDataProvider')]
    public function testValidate(array $spec, Expectation $expectation): void
    {
        $factory     = TestHelper::getContainer()->get(Factory::class);
        $inputFilter = $factory->createInputFilter($spec);

        $result = $inputFilter->validate($expectation->input);

        self::assertSame($expectation->valid, $result->valid());
        self::assertSame($expectation->expect, $result->value());
        self::assertValidEntry($result, $expectation->validKeys);
        self::assertInvalidEntry($result, $expectation->invalidKeys);
    }

    /** @param list<string> $validKeys */
    private static function assertValidEntry(ValidationResultInterface $result, array $validKeys): void
    {
        foreach ($validKeys as $keys) {
            $itemResult = self::fetchResultFromDotSeparatedKey($keys, $result);
            self::assertTrue(
                $itemResult->valid(),
                sprintf(
                    'The input `%s` was expected to be valid, but it was invalid',
                    $keys,
                ),
            );
        }
    }

    /** @param list<string> $invalidKeys */
    private static function assertInvalidEntry(ValidationResultInterface $result, array $invalidKeys): void
    {
        foreach ($invalidKeys as $keys) {
            $itemResult = self::fetchResultFromDotSeparatedKey($keys, $result);
            self::assertFalse(
                $itemResult->valid(),
                sprintf(
                    'The input `%s` was expected to be invalid, but it was valid',
                    $keys,
                ),
            );
        }
    }

    private static function fetchResultFromDotSeparatedKey(
        string $key,
        ValidationResultInterface $result,
    ): ValidationResultInterface {
        $return = $result;
        foreach (explode('.', $key) as $itemKey) {
            self::assertInstanceOf(InputFilterValidationResult::class, $return);
            $return = $return->resultFor($itemKey);
        }

        return $return;
    }
}
