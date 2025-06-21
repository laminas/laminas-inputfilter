<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Error;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterFactory;
use Laminas\InputFilter\OptionalInputFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class InputFilterFactoryTest extends TestCase
{
    /** @param class-string $className */
    #[DataProvider('supportedClassProvider')]
    public function testSupportedClasses(string $className): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())
            ->method('get')
            ->with(Factory::class)
            ->willReturn(FactoryTestHelper::createInputFilterFactory());

        $factory = new InputFilterFactory();

        $result = $factory($container, $className);

        $this->assertInstanceOf($className, $result);
    }

    /**
     * @return class-string[][]
     */
    public static function supportedClassProvider(): array
    {
        return [
            [InputFilter::class],
            [CollectionInputFilter::class],
            [OptionalInputFilter::class],
        ];
    }

    public function testUnsupportedClass(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('UnsupportedClassName');

        $factory = new InputFilterFactory();

        $factory($this->createMock(ContainerInterface::class), 'UnsupportedClassName');
    }
}
