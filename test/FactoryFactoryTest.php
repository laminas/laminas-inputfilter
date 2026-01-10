<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\FactoryFactory;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class FactoryFactoryTest extends TestCase
{
    public function testInvoke(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $container->expects(self::exactly(3))
            ->method('get')
            ->willReturnMap(
                [
                    [FilterPluginManager::class, new FilterPluginManager($container)],
                    [ValidatorPluginManager::class, new ValidatorPluginManager($container)],
                    [InputFilterPluginManager::class, new InputFilterPluginManager($container)],
                ]
            );

        $factory = new FactoryFactory();

        $filters = $factory($container);
        self::assertInstanceOf(Factory::class, $filters);
    }
}
