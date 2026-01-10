<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

use function assert;
use function class_exists;
use function count;

/** @psalm-import-type ServiceManagerConfiguration from ServiceManager */
final class InputFilterPluginManagerCompatibilityTest extends TestCase
{
    /** @param ServiceManagerConfiguration $config */
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

    public function testInputFiltersAreNotSharedByDefault(): void
    {
        $manager     = self::getPluginManager();
        $inputFilter = $manager->get(InputFilter::class);
        self::assertInstanceOf(InputFilter::class, $inputFilter);

        $anotherOne = $manager->get(InputFilter::class);

        self::assertNotSame($inputFilter, $anotherOne);
    }

    public function testRegisteringInvalidElementRaisesException(): void
    {
        $this->expectException(InvalidServiceException::class);
        self::getPluginManager()->configure([
            'services' => [
                'test' => $this,
            ],
        ]);
    }

    public function testLoadingInvalidElementRaisesException(): void
    {
        $manager = self::getPluginManager();
        $manager->configure([
            'invokables' => [
                'test' => stdClass::class,
            ],
        ]);
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
        $reflection = new ReflectionClass($manager);
        $constant   = $reflection->getConstant('DEFAULT_CONFIGURATION');
        self::assertIsArray($constant);
        $aliases = $constant['aliases'] ?? [];
        self::assertIsArray($aliases);
        self::assertGreaterThan(0, count($aliases));

        $data = [];
        foreach ($aliases as $alias => $expected) {
            self::assertIsString($alias);
            self::assertIsString($expected);
            assert(class_exists($expected));

            $data[] = [$alias, $expected];
        }

        return $data;
    }
}
