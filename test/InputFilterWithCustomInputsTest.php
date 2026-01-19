<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\StringTrim;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\Validator\Callback;
use LaminasTest\InputFilter\TestAsset\InputInterfaceImplementation;
use LaminasTest\InputFilter\TestAsset\InputInterfaceImplementationFactory;
use PHPUnit\Framework\TestCase;

final class InputFilterWithCustomInputsTest extends TestCase
{
    public function testAnInputFilterUsingCustomInputsHasTheExpectedBehaviour(): void
    {
        $container = TestHelper::getContainer();
        $inputs    = $container->get(InputFilterPluginManager::class);
        $inputs->configure([
            'factories' => [
                InputInterfaceImplementation::class => InputInterfaceImplementationFactory::class,
            ],
        ]);

        $inputFilter = $inputs->get(InputFilter::class);
        $inputFilter->add([
            'name'       => 'a',
            'filters'    => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                [
                    'name'    => Callback::class,
                    'options' => [
                        'callback' => static fn (mixed $value): bool => $value === 'kermit',
                    ],
                ],
            ],
        ]);
        $inputFilter->add([
            'name'       => 'b',
            'filters'    => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                [
                    'name'    => Callback::class,
                    'options' => [
                        'callback' => static fn (mixed $value): bool => $value === 'gonzo',
                    ],
                ],
            ],
        ]);

        $inputFilter->setData(['a' => ' kermit ', 'b' => ' gonzo ']);
        self::assertTrue($inputFilter->isValid());
        self::assertSame(['a' => 'kermit', 'b' => 'gonzo'], $inputFilter->getValues());

        $inputFilter->setData(['a' => ' fred ', 'b' => ' bob ']);
        self::assertFalse($inputFilter->isValid());
        self::assertArrayHasKey('a', $inputFilter->getMessages()->toArray());
        self::assertArrayHasKey('b', $inputFilter->getMessages()->toArray());
    }
}
