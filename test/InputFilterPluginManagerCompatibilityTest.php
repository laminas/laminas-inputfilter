<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

use function assert;
use function class_exists;

final class InputFilterPluginManagerCompatibilityTest extends TestCase
{
    protected static function getPluginManager(array $config = []): InputFilterPluginManager
    {
        $serviceManager = new ServiceManager($config);
        $serviceManager->setService(
            Factory::class,
            new Factory(
                new FilterPluginManager($serviceManager),
                new ValidatorPluginManager($serviceManager),
                new InputFilterPluginManager($serviceManager)
            )
        );

        return new InputFilterPluginManager($serviceManager);
    }

    public function testShareByDefaultAndSharedByDefault(): void
    {
        $manager        = self::getPluginManager();
        $reflection     = new ReflectionClass($manager);
        $shareByDefault = $sharedByDefault = true;

        foreach ($reflection->getProperties() as $prop) {
            if ($prop->getName() === 'shareByDefault') {
                /** @var mixed $shareByDefault */
                $shareByDefault = $prop->getValue($manager);
                self::assertIsBool($shareByDefault);
            }
            if ($prop->getName() === 'sharedByDefault') {
                /** @var mixed $sharedByDefault */
                $sharedByDefault = $prop->getValue($manager);
                self::assertIsBool($sharedByDefault);
            }
        }

        self::assertSame(
            $shareByDefault,
            $sharedByDefault,
            'Values of shareByDefault and sharedByDefault do not match'
        );
    }

    public function testRegisteringInvalidElementRaisesException(): void
    {
        $this->expectException(InvalidServiceException::class);
        /** @psalm-suppress InvalidArgument */
        self::getPluginManager()->setService('test', $this);
    }

    public function testLoadingInvalidElementRaisesException(): void
    {
        $manager = self::getPluginManager();
        $manager->setInvokableClass('test', stdClass::class);
        $this->expectException(InvalidServiceException::class);
        $manager->get('test');
    }

    /** @param class-string $expected */
    #[DataProvider('aliasProvider')]
    public function testPluginAliasesResolve(string $alias, string $expected): void
    {
        self::assertInstanceOf($expected, self::getPluginManager()->get($alias), "Alias '$alias' does not resolve'");
    }

    /**
     * @return list<array{0: string, 1: class-string}>
     */
    public static function aliasProvider(): array
    {
        $manager    = self::getPluginManager();
        $reflection = new ReflectionProperty($manager, 'aliases');
        $data       = [];
        foreach ($reflection->getValue($manager) as $alias => $expected) {
            self::assertIsString($alias);
            self::assertIsString($expected);
            assert(class_exists($expected));

            $data[] = [$alias, $expected];
        }
        return $data;
    }
}
