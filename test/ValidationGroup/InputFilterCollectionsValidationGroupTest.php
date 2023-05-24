<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\ValidationGroup;

use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;

final class InputFilterCollectionsValidationGroupTest extends TestCase
{
    private InputFilter $inputFilter;

    protected function setUp(): void
    {
        parent::setUp();

        $collection = new CollectionInputFilter();
        $collection->setIsRequired(true);

        $first = new Input('first');
        $first->setRequired(true);
        $second = new Input('second');
        $second->setRequired(true);

        $nestedFilter = new InputFilter();
        $nestedFilter->add($first);
        $nestedFilter->add($second);
        $collection->setInputFilter($nestedFilter);

        $this->inputFilter = new InputFilter();
        $this->inputFilter->add($collection, 'stuff');
    }

    /** @return array<string, array{0: int|null}> */
    public static function collectionCountProvider(): array
    {
        return [
            'Collection Count: None'  => [null],
            'Collection Count: One'   => [1],
            'Collection Count: Two'   => [2],
            'Collection Count: Three' => [3],
            'Collection Count: Four'  => [4],
        ];
    }

    private function setCollectionCount(?int $count): void
    {
        if ($count === null) {
            return;
        }

        $collection = $this->inputFilter->get('stuff');
        self::assertInstanceOf(CollectionInputFilter::class, $collection);
        $collection->setCount($count);
    }

    #[DataProvider('collectionCountProvider')]
    public function testIncompleteDataFailsValidation(?int $count): void
    {
        $this->setCollectionCount($count);
        $this->inputFilter->setData([
            'stuff' => [
                ['first' => 'Foo'],
            ],
        ]);
        self::assertFalse($this->inputFilter->isValid());
    }

    #[DataProvider('collectionCountProvider')]
    public function testCompleteDataPassesValidation(?int $count): void
    {
        $this->setCollectionCount($count);
        $this->inputFilter->setData([
            'stuff' => [
                ['first' => 'Foo', 'second' => 'Bar'],
                ['first' => 'Foo', 'second' => 'Bar'],
                ['first' => 'Foo', 'second' => 'Bar'],
                ['first' => 'Foo', 'second' => 'Bar'],
            ],
        ]);

        self::assertTrue($this->inputFilter->isValid());
    }

    #[DataProvider('collectionCountProvider')]
    public function testValidationFailsForCollectionItemValidity(?int $count): void
    {
        $this->setCollectionCount($count);
        $this->inputFilter->setData([
            'stuff' => [
                ['first' => 'Foo', 'second' => 'Bar'],
                ['first' => '', 'second' => 'Bar'],
                ['first' => 'Foo', 'second' => ''],
                ['first' => '', 'second' => ''],
            ],
        ]);

        self::assertFalse($this->inputFilter->isValid());
    }

    #[DataProvider('collectionCountProvider')]
    public function testValidationGroupWithCollectionInputFilter(?int $count): void
    {
        $this->setCollectionCount($count);
        $collection = $this->inputFilter->get('stuff');
        self::assertInstanceOf(CollectionInputFilter::class, $collection);
        $collection->getInputFilter()->setValidationGroup('first');

        $this->inputFilter->setData([
            'stuff' => [
                ['first' => 'Foo'],
                ['first' => 'Foo'],
                ['first' => 'Foo'],
                ['first' => 'Foo'],
            ],
        ]);

        self::assertTrue($this->inputFilter->isValid());
    }

    #[DataProvider('collectionCountProvider')]
    public function testValidationGroupViaCollection(?int $count): void
    {
        $this->setCollectionCount($count);
        $collection = $this->inputFilter->get('stuff');
        self::assertInstanceOf(CollectionInputFilter::class, $collection);
        /** @psalm-suppress InvalidArgument */
        $collection->setValidationGroup([
            0 => 'first',
            1 => 'second',
            2 => 'first',
            3 => 'first',
        ]);

        $this->inputFilter->setData([
            'stuff' => [
                ['first' => 'Foo'],
                ['second' => 'Foo'],
                ['first' => 'Foo'],
                ['first' => 'Foo'],
            ],
        ]);

        self::assertTrue($this->inputFilter->isValid());
    }

    /**
     * This test documents existing behaviour - the validation group must be set for elements 0 through 3
     */
    #[DataProvider('collectionCountProvider')]
    public function testValidationGroupViaCollectionMustSpecifyAllKeys(?int $count): void
    {
        $this->setCollectionCount($count);
        $collection = $this->inputFilter->get('stuff');
        self::assertInstanceOf(CollectionInputFilter::class, $collection);

        /** @psalm-suppress InvalidArgument */
        $collection->setValidationGroup([
            0 => 'first',
        ]);

        $this->inputFilter->setData([
            'stuff' => [
                ['first' => 'Foo'],
                ['first' => 'Foo'],
                ['first' => 'Foo'],
                ['first' => 'Foo'],
            ],
        ]);

        set_error_handler(function (int $num, string $msg): never {
            throw new RuntimeException($msg, $num);
        });

        try {
            $this->inputFilter->isValid();
            self::fail('A warning was not issued');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Undefined array key 1', $e->getMessage());
        } finally {
            restore_error_handler();
        }
    }

    #[DataProvider('collectionCountProvider')]
    public function testValidationGroupViaTopLevelInputFilter(?int $count): void
    {
        $this->setCollectionCount($count);
        /** @psalm-suppress InvalidArgument */
        $this->inputFilter->setValidationGroup([
            'stuff' => [
                0 => 'first',
                1 => 'second',
                2 => 'first',
                3 => 'first',
            ],
        ]);

        $this->inputFilter->setData([
            'stuff' => [
                ['first' => 'Foo'],
                ['second' => 'Foo'],
                ['first' => 'Foo'],
                ['first' => 'Foo'],
            ],
        ]);

        self::assertTrue($this->inputFilter->isValid());
    }
}
