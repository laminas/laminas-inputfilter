<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter;
use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterChainInterface;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\InputFilter\InputInterface;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Validator\Digits;
use Laminas\Validator\Exception\InvalidSpecificationArrayException;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorChainInterface;
use Laminas\Validator\ValidatorPluginManager;
use LaminasTest\InputFilter\TestAsset\CustomInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionObject;
use TypeError;

#[CoversClass(Factory::class)]
final class FactoryTest extends TestCase
{
    public function testCreateInputWithInvalidDataTypeThrowsInvalidArgumentException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(TypeError::class);
        /** @psalm-suppress InvalidArgument */
        $factory->createInput('invalid_value');
    }

    public function testCreateInputWithTypeAsAnUnknownPluginAndNotExistsAsClassNameThrowException(): void
    {
        $container     = $this->createMock(ContainerInterface::class);
        $pluginManager = new InputFilterPluginManager($container);
        $factory       = new Factory(
            new FilterPluginManager($container),
            new ValidatorPluginManager($container),
            $pluginManager
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Input factory expects the "type" to be a valid class or a plugin name; received "foo"'
        );
        $factory->createInput([
            'type' => 'foo',
        ]);
    }

    public function testCreateInputWithTypeAsAnInvalidPluginInstanceThrowException(): void
    {
        $container     = $this->createMock(ContainerInterface::class);
        $pluginManager = new InputFilterPluginManager($container);
        $factory       = new Factory(
            new FilterPluginManager($container),
            new ValidatorPluginManager($container),
            $pluginManager
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Input factory expects the "type" to be a valid class or a plugin name; received "fooPlugin"'
        );
        $factory->createInput([
            'type' => 'fooPlugin',
        ]);
    }

    public function testCreateInputWithTypeAsAnInvalidClassInstanceThrowException(): void
    {
        $factory = $this->createDefaultFactory();
        $type    = 'stdClass';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'You will need to create your own factory for custom inputs '
            . 'because we cannot know what your constructor arguments might be'
        );
        $factory->createInput([
            'type' => $type,
        ]);
    }

    public function testCreateInputWithInvalidFilterSpecType(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(InvalidArgumentException::class);
        /** @psalm-suppress InvalidArgument */
        $factory->createInput([
            'filters' => 'invalid_value',
        ]);
    }

    public function testCreateInputWithEmptyFilterSpecIsExceptional(): void
    {
        $factory = $this->createDefaultFactory();
        $this->expectException(Filter\Exception\InvalidSpecificationArrayException::class);
        /** @psalm-suppress InvalidArgument */
        $factory->createInput([
            'filters' => [
                [
                    // empty
                ],
            ],
        ]);
    }

    public function testFilterSpecificationsAreValidatedByUpstreamFilter(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(Filter\Exception\InvalidSpecificationArrayException::class);
        $factory->createInput([
            'filters' => [
                'invalid value',
            ],
        ]);
    }

    public function testCreateInputWithValidatorsAsAnInvalidTypeThrowException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(TypeError::class);
        /** @psalm-suppress InvalidArgument */
        $factory->createInput([
            'validators' => 'invalid_value',
        ]);
    }

    public function testCreateInputWithValidatorsAsAnSpecificationWithMissingNameThrowsException(): void
    {
        $factory = $this->createDefaultFactory();
        $this->expectException(InvalidSpecificationArrayException::class);
        /** @psalm-suppress InvalidArgument */
        $factory->createInput([
            'validators' => [
                [
                    // empty
                ],
            ],
        ]);
    }

    public function testCreateInputWithValidatorsAsAnCollectionOfInvalidTypesThrowException(): void
    {
        $factory = $this->createDefaultFactory();
        $this->expectException(InvalidSpecificationArrayException::class);
        /** @psalm-suppress InvalidArgument */
        $factory->createInput([
            'validators' => [
                'invalid value',
            ],
        ]);
    }

    public function testFactoryCreatesFilterChainWithComposedPluginManagerWhenCreatingNewInputObjects(): void
    {
        $container = TestHelper::getContainer();
        $factory   = $container->get(Factory::class);
        $plugins   = $container->get(FilterPluginManager::class);

        $input = $factory->createInput([
            'name' => 'foo',
        ]);

        $inputFilterChain = $input->getFilterChain();
        self::assertInstanceOf(FilterChain::class, $inputFilterChain);
        self::assertSame(
            $plugins,
            TestHelper::getFilterPluginManagerFromFilterChain($inputFilterChain),
        );
    }

    public function testFactoryCreatesValidatorChainWithComposedPluginManagerWhenCreatingNewInputObjects(): void
    {
        $container = TestHelper::getContainer();
        $factory   = $container->get(Factory::class);
        $plugins   = $factory->getValidatorPluginManager();
        $input     = $factory->createInput([
            'name' => 'foo',
        ]);

        $inputValidatorChain = $input->getValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $inputValidatorChain);
        self::assertSame(
            $plugins,
            $inputValidatorChain->getPluginManager(),
        );
    }

    public function testFactoryInjectsComposedFilterAndValidatorChainsIntoInputObjectsWhenCreatingNewInputFilterObjects(): void // phpcs:ignore
    {
        $container        = TestHelper::getContainer();
        $factory          = $container->get(Factory::class);
        $filterPlugins    = $container->get(FilterPluginManager::class);
        $validatorPlugins = $container->get(ValidatorPluginManager::class);

        $inputFilter = $factory->create([
            'foo' => [
                'name' => 'foo',
            ],
        ]);

        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);
        self::assertCount(1, $inputFilter);
        $input = $inputFilter->get('foo');
        self::assertInstanceOf(InputInterface::class, $input);
        $inputFilterChain = $input->getFilterChain();
        self::assertInstanceOf(FilterChain::class, $inputFilterChain);
        $inputValidatorChain = $input->getValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $inputValidatorChain);
        self::assertSame(
            $filterPlugins,
            (new ReflectionObject($inputFilterChain))->getProperty('plugins')->getValue($inputFilterChain)
        );
        self::assertSame($validatorPlugins, $inputValidatorChain->getPluginManager());
    }

    public function testFactoryWillCreateInputWithSuggestedFilters(): void
    {
        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput([
            'name'    => 'foo',
            'filters' => [
                [
                    'name' => Filter\StringTrim::class,
                ],
                [
                    'name'    => Filter\StringToLower::class,
                    'options' => [
                        'encoding' => 'ISO-8859-1',
                    ],
                ],
            ],
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        self::assertEquals('foo', $input->getName());
        $filterChain = $input->getFilterChain();
        self::assertInstanceOf(FilterChain::class, $filterChain);
        self::assertCount(2, $filterChain);

        $input->setValue('   Encodable with ISO-8859-1  ');

        self::assertEquals('encodable with iso-8859-1', $input->getValue());

        $input->setValue('αλώπηξ not encodable with iso-8859-1');

        self::assertNotEquals(
            'αλώπηξ not encodable with iso-8859-1',
            $input->getValue(),
            'This is likely failing due to the StringToLower not having the encoding set'
        );
    }

    /** @return array<array-key, array{string, bool}> */
    public static function digitStringProvider(): array
    {
        return [
            ['', false],
            ['aaa', false],
            ['123', true],
            ['12345', true],
            ['12', false],
            ['123456', false],
        ];
    }

    #[DataProvider('digitStringProvider')]
    public function testFactoryWillCreateInputWithSuggestedValidators(string $value, bool $isValid): void
    {
        $factory = $this->createDefaultFactory();
        $digits  = new Digits();
        $input   = $factory->createInput([
            'name'       => 'foo',
            'validators' => [
                [
                    'name' => NotEmpty::class,
                ],
                $digits,
                [
                    'name'    => StringLength::class,
                    'options' => [
                        'min' => 3,
                        'max' => 5,
                    ],
                ],
            ],
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        self::assertEquals('foo', $input->getName());
        $chain = $input->getValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $chain);
        self::assertCount(3, $chain);

        $input->setValue($value);

        self::assertSame($isValid, $input->isValid());
    }

    public function testFactoryWillCreateInputWithSuggestedRequiredFlagAndAlternativeAllowEmptyFlag(): void
    {
        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput([
            'name'        => 'foo',
            'required'    => false,
            'allow_empty' => false,
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        self::assertFalse($input->isRequired());
        self::assertFalse($input->allowEmpty());
    }

    public function testFactoryWillCreateInputWithSuggestedAllowEmptyFlagAndImpliesRequiredFlag(): void
    {
        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput([
            'name'        => 'foo',
            'allow_empty' => true,
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        self::assertTrue($input->allowEmpty());
        self::assertFalse($input->isRequired());
    }

    public function testFactoryWillCreateInputWithSuggestedName(): void
    {
        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput([
            'name' => 'foo',
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        self::assertEquals('foo', $input->getName());
    }

    public function testFactoryWillCreateInputWithContinueIfEmptyFlag(): void
    {
        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput([
            'name'              => 'foo',
            'continue_if_empty' => true,
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        self::assertTrue($input->continueIfEmpty());
    }

    public function testFactoryAcceptsInputInterface(): void
    {
        $factory = $this->createDefaultFactory();
        $input   = new Input(TestHelper::createFilterChain(), TestHelper::createValidatorChain());

        $inputFilter = $factory->createInputFilter([
            'foo' => $input,
        ]);

        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);
        self::assertTrue($inputFilter->has('foo'));
        self::assertEquals($input, $inputFilter->get('foo'));
    }

    public function testFactoryAcceptsInputFilterInterface(): void
    {
        $factory = $this->createDefaultFactory();
        $input   = new InputFilter($factory);

        $inputFilter = $factory->createInputFilter([
            'foo' => $input,
        ]);

        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);
        self::assertTrue($inputFilter->has('foo'));
        self::assertEquals($input, $inputFilter->get('foo'));
    }

    public function testFactoryWillCreateInputFilterAndAllInputObjectsFromGivenConfiguration(): void
    {
        $serviceManager           = TestHelper::getContainer();
        $inputFilterPluginManager = $serviceManager->get(InputFilterPluginManager::class);
        $inputFilterPluginManager->configure([
            'factories' => [
                CustomInput::class => static fn (): CustomInput => new CustomInput(
                    TestHelper::createFilterChain(),
                    TestHelper::createValidatorChain(),
                ),
            ],
        ]);

        $factory = $serviceManager->get(Factory::class);

        $inputFilter = $factory->createInputFilter([
            'foo'  => [
                'name'       => 'foo',
                'required'   => false,
                'validators' => [
                    [
                        'name' => NotEmpty::class,
                    ],
                    [
                        'name'    => StringLength::class,
                        'options' => [
                            'min' => 3,
                            'max' => 5,
                        ],
                    ],
                ],
            ],
            'bar'  => [
                'allow_empty' => true,
                'filters'     => [
                    [
                        'name' => Filter\StringTrim::class,
                    ],
                    [
                        'name'    => Filter\StringToLower::class,
                        'options' => [
                            'encoding' => 'ISO-8859-1',
                        ],
                    ],
                ],
            ],
            'baz'  => [
                'type' => InputFilter::class,
                'foo'  => [
                    'name'       => 'foo',
                    'required'   => false,
                    'validators' => [
                        [
                            'name' => NotEmpty::class,
                        ],
                        [
                            'name'    => StringLength::class,
                            'options' => [
                                'min' => 3,
                                'max' => 5,
                            ],
                        ],
                    ],
                ],
                'bar'  => [
                    'allow_empty' => true,
                    'filters'     => [
                        [
                            'name' => Filter\StringTrim::class,
                        ],
                        [
                            'name'    => Filter\StringToLower::class,
                            'options' => [
                                'encoding' => 'ISO-8859-1',
                            ],
                        ],
                    ],
                ],
            ],
            'bat'  => [
                'type' => CustomInput::class,
                'name' => 'bat',
            ],
            'zomg' => [
                'name'              => 'zomg',
                'continue_if_empty' => true,
            ],
        ]);
        self::assertInstanceOf(InputFilter::class, $inputFilter);
        self::assertCount(5, $inputFilter);

        foreach (['foo', 'bar', 'baz', 'bat', 'zomg'] as $name) {
            $input = $inputFilter->get($name);

            switch ($name) {
                case 'foo':
                    self::assertInstanceOf(Input::class, $input);
                    self::assertFalse($input->isRequired());
                    $validatorChain = $input->getValidatorChain();
                    self::assertInstanceOf(ValidatorChain::class, $validatorChain);
                    self::assertCount(2, $validatorChain);
                    break;
                case 'bar':
                    self::assertInstanceOf(Input::class, $input);
                    self::assertTrue($input->allowEmpty());
                    $filterChain = $input->getFilterChain();
                    self::assertInstanceOf(FilterChain::class, $filterChain);
                    self::assertCount(2, $filterChain);
                    break;
                case 'baz':
                    self::assertInstanceOf(InputFilter::class, $input);
                    self::assertCount(2, $input);
                    $foo = $input->get('foo');
                    self::assertInstanceOf(Input::class, $foo);
                    self::assertFalse($foo->isRequired());
                    $validatorChain = $foo->getValidatorChain();
                    self::assertInstanceOf(ValidatorChain::class, $validatorChain);
                    self::assertCount(2, $validatorChain);
                    $bar = $input->get('bar');
                    self::assertInstanceOf(Input::class, $bar);
                    self::assertTrue($bar->allowEmpty());
                    $filterChain = $bar->getFilterChain();
                    self::assertInstanceOf(FilterChain::class, $filterChain);
                    self::assertCount(2, $filterChain);
                    break;
                case 'bat':
                    self::assertInstanceOf(CustomInput::class, $input);
                    self::assertEquals('bat', $input->getName());
                    break;
                case 'zomg':
                    self::assertInstanceOf(Input::class, $input);
                    self::assertTrue($input->continueIfEmpty());
            }
        }
    }

    public function testFactoryWillCreateInputFilterMatchingInputNameWhenNotSpecified(): void
    {
        $factory     = $this->createDefaultFactory();
        $inputFilter = $factory->createInputFilter([
            ['name' => 'foo'],
        ]);

        self::assertTrue($inputFilter->has('foo'));
        self::assertInstanceOf(Input::class, $inputFilter->get('foo'));
    }

    public function testFactoryAllowsPassingValidatorChainsInInputSpec(): void
    {
        $factory = $this->createDefaultFactory();
        $chain   = new ValidatorChain();
        $input   = $factory->createInput([
            'name'       => 'foo',
            'validators' => $chain,
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        $test = $input->getValidatorChain();
        self::assertSame($chain, $test);
    }

    public function testFactoryAllowsPassingFilterChainsInInputSpec(): void
    {
        $factory = $this->createDefaultFactory();
        $chain   = TestHelper::createFilterChain();
        $input   = $factory->createInput([
            'name'    => 'foo',
            'filters' => $chain,
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        $test = $input->getFilterChain();
        self::assertSame($chain, $test);
    }

    public function testFactoryAcceptsCollectionInputFilter(): void
    {
        $factory = $this->createDefaultFactory();

        /** @var CollectionInputFilter $inputFilter */
        $inputFilter = $factory->createInputFilter([
            'type'        => CollectionInputFilter::class,
            'required'    => true,
            'inputfilter' => new InputFilter(
                $factory
            ),
            'count'       => 3,
        ]);

        self::assertInstanceOf(CollectionInputFilter::class, $inputFilter);
        self::assertInstanceOf(InputFilter::class, $inputFilter->getInputFilter());
        self::assertTrue($inputFilter->getIsRequired());
        self::assertEquals(3, $inputFilter->getCount());
    }

    public function testFactoryWillCreateInputWithErrorMessage(): void
    {
        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput([
            'name'          => 'foo',
            'error_message' => 'My custom error message',
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        self::assertEquals('My custom error message', $input->getErrorMessage());
    }

    public function testFactoryWillNotGetPrioritySetting(): void
    {
        //Reminder: Priority at which to enqueue filter; defaults to 1000 (higher executes earlier)
        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput([
            'name'    => 'foo',
            'filters' => [
                [
                    'name'     => 'StringTrim',
                    'priority' => FilterChainInterface::DEFAULT_PRIORITY - 1, // 999
                ],
                [
                    'name'     => 'StringToUpper',
                    'priority' => FilterChainInterface::DEFAULT_PRIORITY + 1, //1001
                ],
                [
                    'name' => 'StringToLower', // default priority 1000
                ],
            ],
        ]);
        self::assertInstanceOf(InputInterface::class, $input);

        // We should have 3 filters
        $filterChain = $input->getFilterChain();
        self::assertInstanceOf(FilterChain::class, $filterChain);
        self::assertCount(3, $filterChain);

        // Filters should pop in the following order:
        // string_to_upper (1001), string_to_lower (1000), string_trim (999)
        $index = 0;
        foreach ($filterChain as $filter) {
            switch ($index) {
                case 0:
                    self::assertInstanceOf(Filter\StringToUpper::class, $filter);
                    break;
                case 1:
                    self::assertInstanceOf(Filter\StringToLower::class, $filter);
                    break;
                case 2:
                    self::assertInstanceOf(Filter\StringTrim::class, $filter);
                    break;
            }
            $index++;
        }

        self::assertSame(3, $index);
    }

    public function testFactoryValidatorsPriority(): void
    {
        $order = 0;

        //Reminder: Priority at which to enqueue validator; defaults to 1 (higher executes earlier)
        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput([
            'name'       => 'foo',
            'validators' => [
                [
                    'name'     => 'Callback',
                    'priority' => ValidatorChainInterface::DEFAULT_PRIORITY - 1, // 0
                    'options'  => [
                        'callback' => static function () use (&$order): bool {
                            self::assertSame(2, $order);
                            ++$order;

                            return true;
                        },
                    ],
                ],
                [
                    'name'     => 'Callback',
                    'priority' => ValidatorChainInterface::DEFAULT_PRIORITY + 1, // 2
                    'options'  => [
                        'callback' => static function () use (&$order): true {
                            self::assertSame(0, $order);
                            ++$order;

                            return true;
                        },
                    ],
                ],
                [
                    'name'    => 'Callback', // default priority 1
                    'options' => [
                        'callback' => static function () use (&$order): bool {
                            self::assertSame(1, $order);
                            ++$order;

                            return true;
                        },
                    ],
                ],
            ],
        ]);
        self::assertInstanceOf(InputInterface::class, $input);

        // We should have 3 validators
        $validatorChain = $input->getValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $validatorChain);
        self::assertCount(3, $validatorChain);

        $input->setValue(['foo' => false]);
        self::assertTrue($input->isValid());
    }

    public function testConflictNameWithInputFilterType(): void
    {
        $factory = $this->createDefaultFactory();

        $inputFilter = $factory->createInputFilter(
            [
                'type' => [
                    'required' => true,
                ],
            ]
        );

        self::assertInstanceOf(InputFilter::class, $inputFilter);
        self::assertTrue($inputFilter->has('type'));
    }

    public function testCanSetInputErrorMessage(): void
    {
        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput([
            'name'          => 'test',
            'type'          => Input::class,
            'error_message' => 'Custom error message',
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        self::assertEquals('Custom error message', $input->getErrorMessage());
    }

    public function testSetsBreakChainOnFailure(): void
    {
        $factory = $this->createDefaultFactory();

        self::assertTrue($factory->createInput(['break_on_failure' => true])->breakOnFailure());

        self::assertFalse($factory->createInput(['break_on_failure' => false])->breakOnFailure());
    }

    public function testCanCreateInputFilterWithNullInputs(): void
    {
        $factory = $this->createDefaultFactory();

        $inputFilter = $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
            ],
            'bar' => null,
            'baz' => [
                'name' => 'baz',
            ],
        ]);

        self::assertInstanceOf(InputFilter::class, $inputFilter);
        self::assertCount(2, $inputFilter);
        self::assertTrue($inputFilter->has('foo'));
        self::assertFalse($inputFilter->has('bar'));
        self::assertTrue($inputFilter->has('baz'));
    }

    public function testCanCreateInputFromProvider(): void
    {
        /** @var InputProviderInterface&MockObject $provider */
        $provider = $this->createMock(InputProviderInterface::class);

        $provider
            ->expects(self::any())
            ->method('getInputSpecification')
            ->willReturn(['name' => 'foo']);

        $factory = $this->createDefaultFactory();
        $input   = $factory->createInput($provider);

        self::assertInstanceOf(InputInterface::class, $input);
    }

    public function testCanCreateInputFilterFromProvider(): void
    {
        /** @var InputFilterProviderInterface&MockObject $provider */
        $provider = $this->createMock(InputFilterProviderInterface::class);
        $provider
            ->expects(self::any())
            ->method('getInputFilterSpecification')
            ->willReturn([
                'foo' => [
                    'name'     => 'foo',
                    'required' => false,
                ],
                'baz' => [
                    'name'     => 'baz',
                    'required' => true,
                ],
            ]);

        $factory     = $this->createDefaultFactory();
        $inputFilter = $factory->createInputFilter($provider);

        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);
    }

    public function testSuggestedTypeMayBePluginNameInInputFilterPluginManager(): void
    {
        $container     = TestHelper::getContainer();
        $pluginManager = $container->get(InputFilterPluginManager::class);
        $pluginManager->configure([
            'services' => [
                'bar' => new Input(TestHelper::createFilterChain(), TestHelper::createValidatorChain(), 'bar'),
            ],
        ]);
        $factory = $container->get(Factory::class);

        $input = $factory->createInput([
            'type' => 'bar',
        ]);

        self::assertInstanceOf(InputInterface::class, $input);
        self::assertSame('bar', $input->getName());
    }

    public function testInputFromPluginManagerMayBeFurtherConfiguredWithSpec(): void
    {
        // An input with the name "bar"
        $barInput = new Input(TestHelper::createFilterChain(), TestHelper::createValidatorChain(), 'bar');

        $container     = TestHelper::getContainer();
        $pluginManager = $container->get(InputFilterPluginManager::class);
        $pluginManager->configure([
            'services' => [
                'bar' => $barInput,
            ],
        ]);

        $factory = $container->get(Factory::class);
        self::assertTrue($barInput->isRequired());

        $input = $factory->createInput([
            'type'     => 'bar',
            'required' => false,
        ]);

        self::assertInstanceOf(InputInterface::class, $input);
        self::assertFalse($input->isRequired());
        self::assertSame('bar', $input->getName());
    }

    public function testCreateInputFilterConfiguredNameWhenSpecIsIntegerIndexed(): void
    {
        $factory     = $this->createDefaultFactory();
        $inputFilter = $factory->createInputFilter([
            1 => [
                'type' => InputFilter::class,
                'name' => 'foo',
            ],
        ]);

        self::assertTrue($inputFilter->has('foo'));
    }

    public function testCreateInputFilterUsesAssociatedNameMappingOverConfiguredName(): void
    {
        $factory     = $this->createDefaultFactory();
        $inputFilter = $factory->createInputFilter([
            'foo' => [
                'type' => InputFilter::class,
                'name' => 'bar',
            ],
        ]);

        self::assertTrue($inputFilter->has('foo'));
        self::assertFalse($inputFilter->has('bar'));
    }

    public function testCreateInputFilterUsesConfiguredNameForNestedInputFilters(): void
    {
        $factory     = $this->createDefaultFactory();
        $inputFilter = $factory->createInputFilter([
            0 => [
                'type' => InputFilter::class,
                'name' => 'bar',
                '0'    => [
                    'name' => 'bat',
                ],
                '1'    => [
                    'name' => 'baz',
                ],
            ],
            1 => [
                'type'         => CollectionInputFilter::class,
                'name'         => 'foo',
                'input_filter' => [
                    '0' => [
                        'name' => 'bat',
                    ],
                ],
            ],
        ]);

        self::assertInstanceOf(InputFilter::class, $inputFilter);
        self::assertCount(2, $inputFilter);

        $nestedInputFilter = $inputFilter->get('bar');
        self::assertInstanceOf(InputFilter::class, $nestedInputFilter);
        self::assertCount(2, $nestedInputFilter);
        self::assertTrue($nestedInputFilter->has('bat'));
        self::assertTrue($nestedInputFilter->has('baz'));

        $collection = $inputFilter->get('foo');
        self::assertInstanceOf(CollectionInputFilter::class, $collection);
        $collectionInputFilter = $collection->getInputFilter();
        self::assertInstanceOf(InputFilter::class, $collectionInputFilter);
        self::assertCount(1, $collectionInputFilter);
        self::assertTrue($collectionInputFilter->has('bat'));
    }

    public function testWhenCreateInputPullsInputFromThePluginManagerItMustNotOverwriteFilterAndValidatorChains(): void
    {
        $container                = TestHelper::getContainer();
        $filterPluginManager      = $container->get(FilterPluginManager::class);
        $validatorPlugins         = $container->get(ValidatorPluginManager::class);
        $inputFilterPluginManager = $container->get(InputFilterPluginManager::class);
        $factory                  = $container->get(Factory::class);

        $filterChain    = new FilterChain($filterPluginManager);
        $validatorChain = new ValidatorChain();
        $validatorChain->setPluginManager($validatorPlugins);

        $input = new Input($filterChain, $validatorChain);

        $inputFilterPluginManager->configure([
            'services' => [
                'Some\Test\Input' => $input,
            ],
        ]);

        $spec         = ['type' => 'Some\Test\Input'];
        $createdInput = $factory->createInput($spec);

        self::assertSame(
            $input,
            $createdInput,
            'The input fixture should be available in the input filter plugin manager',
        );

        self::assertSame(
            $filterChain,
            $input->getFilterChain(),
            'The filter chain of the input should not have been changed',
        );

        self::assertSame(
            $validatorChain,
            $input->getValidatorChain(),
            'The validator chain of the input should not have been changed',
        );
    }

    public function testFactoryCanCreateCollectionInputFilterWithRequiredMessage(): void
    {
        $factory = $this->createDefaultFactory();
        $message = 'this is the validation message';

        /** @var CollectionInputFilter $inputFilter */
        $inputFilter = $factory->createInputFilter([
            'type'             => CollectionInputFilter::class,
            'required'         => true,
            'required_message' => $message,
            'count'            => 0,
        ]);

        self::assertInstanceOf(CollectionInputFilter::class, $inputFilter);

        self::assertFalse($inputFilter->isValid());
        self::assertSame([[NotEmpty::IS_EMPTY => $message]], $inputFilter->getMessages()->toArray());

        $inputFilter->setIsRequired(false);
        self::assertTrue($inputFilter->isValid());
        self::assertSame([], $inputFilter->getMessages()->toArray());
    }

    protected function createDefaultFactory(): Factory
    {
        $serviceManager = TestHelper::getContainer();
        $serviceManager->setAllowOverride(true);
        $factory = Factory::new($serviceManager);
        $serviceManager->setService(Factory::class, $factory);

        return $factory;
    }
}
