<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter;
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
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\ValidatorPluginManager;
use LaminasTest\InputFilter\TestAsset\CustomInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
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
        $type          = 'foo';
        $pluginManager = $this->createMock(InputFilterPluginManager::class);
        $pluginManager->expects(self::atLeastOnce())
            ->method('has')
            ->with($type)
            ->willReturn(false);

        $container = $this->createMock(ContainerInterface::class);

        $factory = new Factory(
            new FilterPluginManager($container),
            new ValidatorPluginManager($container),
            $pluginManager
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Input factory expects the "type" to be a valid class or a plugin name; received "foo"'
        );
        $factory->createInput([
            'type' => $type,
        ]);
    }

    public function testCreateInputWithTypeAsAnInvalidPluginInstanceThrowException(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $type          = 'fooPlugin';
        $pluginManager = $this->createInputFilterPluginManagerMockForPlugin($type, 'invalid_value');
        $factory       = new Factory(
            new FilterPluginManager($container),
            new ValidatorPluginManager($container),
            $pluginManager
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Input factory expects the "type" to be a class implementing Laminas\InputFilter\InputInterface; '
            . 'received "string"'
        );
        $factory->createInput([
            'type' => $type,
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

    public function testCreateInputWithFiltersAsAnInvalidTypeThrowException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(TypeError::class);
        /** @psalm-suppress InvalidArgument */
        $factory->createInput([
            'filters' => 'invalid_value',
        ]);
    }

    public function testCreateInputWithFiltersAsAnSpecificationWithMissingNameThrowException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid filter specification provided; does not include "name" key');
        /** @psalm-suppress InvalidArgument */
        $factory->createInput([
            'filters' => [
                [
                    // empty
                ],
            ],
        ]);
    }

    public function testCreateInputWithFiltersAsAnCollectionOfInvalidTypesThrowException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid filter specification provided;'
        );
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

    public function testCreateInputWithValidatorsAsAnSpecificationWithMissingNameThrowException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid validator specification provided; does not include "name" key');
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid validator specification provided;'
        );
        /** @psalm-suppress InvalidArgument */
        $factory->createInput([
            'validators' => [
                'invalid value',
            ],
        ]);
    }

    public function testCreateInputFilterWithInvalidDataTypeThrowsInvalidArgumentException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expects an array or Traversable; received "string"');
        /** @psalm-suppress InvalidArgument */
        $factory->createInputFilter('invalid_value');
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
        self::assertSame(
            $plugins,
            TestHelper::getFilterPluginManagerFromFilterChain($inputFilterChain),
        );
    }

    public function testFactoryCreatesValidatorChainWithComposedPluginManagerWhenCreatingNewInputObjects(): void
    {
        $factory = $this->createDefaultFactory();
        $plugins = $factory->getValidatorPluginManager();
        $input   = $factory->createInput([
            'name' => 'foo',
        ]);

        $inputValidatorChain = $input->getValidatorChain();
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
        $inputFilterChain    = $input->getFilterChain();
        $inputValidatorChain = $input->getValidatorChain();
        self::assertSame($filterPlugins, $inputFilterChain->getPluginManager());
        self::assertSame($validatorPlugins, $inputValidatorChain->getPluginManager());
    }

    public function testFactoryWillCreateInputWithSuggestedFilters(): void
    {
        $factory      = $this->createDefaultFactory();
        $htmlEntities = new Filter\HtmlEntities();
        $input        = $factory->createInput([
            'name'    => 'foo',
            'filters' => [
                [
                    'name' => Filter\StringTrim::class,
                ],
                $htmlEntities,
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
        $chain = $input->getFilterChain();
        $index = 0;
        foreach ($chain as $filter) {
            self::assertInstanceOf(Filter\FilterInterface::class, $filter);
            switch ($index) {
                case 0:
                    self::assertInstanceOf(Filter\StringTrim::class, $filter);
                    break;
                case 1:
                    self::assertSame($htmlEntities, $filter);
                    break;
                case 2:
                    self::assertInstanceOf(Filter\StringToLower::class, $filter);
                    self::assertEquals('iso-8859-1', $filter->getEncoding());
                    break;
                default:
                    self::fail('Found more filters than expected');
            }
            $index++;
        }
    }

    public function testFactoryWillCreateInputWithSuggestedValidators(): void
    {
        $factory = $this->createDefaultFactory();
        $digits  = new Validator\Digits();
        $input   = $factory->createInput([
            'name'       => 'foo',
            'validators' => [
                [
                    'name' => Validator\NotEmpty::class,
                ],
                $digits,
                [
                    'name'    => Validator\StringLength::class,
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
        $index = 0;
        foreach ($chain->getValidators() as $validator) {
            $validator = $validator['instance'];
            switch ($index) {
                case 0:
                    self::assertInstanceOf(Validator\NotEmpty::class, $validator);
                    break;
                case 1:
                    self::assertSame($digits, $validator);
                    break;
                case 2:
                    self::assertInstanceOf(Validator\StringLength::class, $validator);
                    self::assertFalse($validator->isValid('aa'));
                    self::assertFalse($validator->isValid('aaaaaa'));
                    self::assertTrue($validator->isValid('aaa'));
                    self::assertTrue($validator->isValid('aaaaa'));
                    break;
                default:
                    self::fail('Found more validators than expected');
            }
            $index++;
        }
        // Assure that previous foreach has been run
        self::assertEquals(3, $index);
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
        $serviceManager           = new ServiceManager();
        $inputFilterPluginManager = new InputFilterPluginManager($serviceManager);
        $inputFilterPluginManager->setFactory(
            CustomInput::class,
            fn () => new CustomInput(TestHelper::createFilterChain(), TestHelper::createValidatorChain())
        );

        $serviceManager->setService(InputFilterPluginManager::class, $inputFilterPluginManager);

        $factory = Factory::new($serviceManager);

        $inputFilter = $factory->createInputFilter([
            'foo'  => [
                'name'       => 'foo',
                'required'   => false,
                'validators' => [
                    [
                        'name' => Validator\NotEmpty::class,
                    ],
                    [
                        'name'    => Validator\StringLength::class,
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
                            'name' => Validator\NotEmpty::class,
                        ],
                        [
                            'name'    => Validator\StringLength::class,
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
                    self::assertCount(2, $input->getValidatorChain());
                    break;
                case 'bar':
                    self::assertInstanceOf(Input::class, $input);
                    self::assertTrue($input->allowEmpty());
                    self::assertCount(2, $input->getFilterChain());
                    break;
                case 'baz':
                    self::assertInstanceOf(InputFilter::class, $input);
                    self::assertCount(2, $input);
                    $foo = $input->get('foo');
                    self::assertInstanceOf(Input::class, $foo);
                    self::assertFalse($foo->isRequired());
                    self::assertCount(2, $foo->getValidatorChain());
                    $bar = $input->get('bar');
                    self::assertInstanceOf(Input::class, $bar);
                    self::assertTrue($bar->allowEmpty());
                    self::assertCount(2, $bar->getFilterChain());
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
        $chain   = new Validator\ValidatorChain();
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
        $chain   = new Filter\FilterChain();
        /** @psalm-suppress DeprecatedMethod removal will be done in Service Manager 4 upgrade */
        $chain->setPluginManager(TestHelper::createFilterPluginManager());
        $input = $factory->createInput([
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
                    'priority' => Filter\FilterChain::DEFAULT_PRIORITY - 1, // 999
                ],
                [
                    'name'     => 'StringToUpper',
                    'priority' => Filter\FilterChain::DEFAULT_PRIORITY + 1, //1001
                ],
                [
                    'name' => 'StringToLower', // default priority 1000
                ],
            ],
        ]);
        self::assertInstanceOf(InputInterface::class, $input);

        // We should have 3 filters
        self::assertEquals(3, $input->getFilterChain()->count());

        // Filters should pop in the following order:
        // string_to_upper (1001), string_to_lower (1000), string_trim (999)
        $index = 0;
        foreach ($input->getFilterChain()->getFilters() as $filter) {
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
                    'priority' => Validator\ValidatorChain::DEFAULT_PRIORITY - 1, // 0
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
                    'priority' => Validator\ValidatorChain::DEFAULT_PRIORITY + 1, // 2
                    'options'  => [
                        'callback' => static function () use (&$order) {
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
        self::assertEquals(3, $input->getValidatorChain()->count());

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

        /**
         * null is not acceptable as an input spec for the psalm type
         *
         * @psalm-suppress InvalidArgument
         */
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
        $serviceManager = new ServiceManager();
        $pluginManager  = new InputFilterPluginManager($serviceManager);
        $pluginManager->setService(
            'bar',
            new Input(TestHelper::createFilterChain(), TestHelper::createValidatorChain(), 'bar')
        );
        $factory = new Factory(
            new FilterPluginManager($serviceManager),
            new ValidatorPluginManager($serviceManager),
            $pluginManager
        );

        $input = $factory->createInput([
            'type' => 'bar',
        ]);

        self::assertInstanceOf(InputInterface::class, $input);
        self::assertSame('bar', $input->getName());
    }

    public function testInputFromPluginManagerMayBeFurtherConfiguredWithSpec(): void
    {
        $serviceManager = new ServiceManager();
        $pluginManager  = new InputFilterPluginManager($serviceManager);
        $pluginManager->setService(
            'bar',
            $barInput   = new Input(TestHelper::createFilterChain(), TestHelper::createValidatorChain(), 'bar')
        );

        $serviceManager->setService(InputFilterPluginManager::class, $pluginManager);

        $factory = Factory::new($serviceManager);
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

        $filterChain = new Filter\FilterChain();
        /** @psalm-suppress DeprecatedMethod removal will be done in Service Manager 4 upgrade */
        $filterChain->setPluginManager($filterPluginManager);

        $validatorChain = new Validator\ValidatorChain();
        $validatorChain->setPluginManager($validatorPlugins);

        $input = new Input($filterChain, $validatorChain);

        $inputFilterPluginManager->setService('Some\Test\Input', $input);

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
        self::assertSame([[NotEmpty::IS_EMPTY => $message]], $inputFilter->getMessages());

        $inputFilter->setIsRequired(false);
        self::assertTrue($inputFilter->isValid());
        self::assertSame([], $inputFilter->getMessages());
    }

    protected function createDefaultFactory(?InputFilterPluginManager $inputFilterPluginManager = null): Factory
    {
        $serviceManager = new ServiceManager();

        $factory = new Factory(
            new FilterPluginManager($serviceManager),
            new ValidatorPluginManager($serviceManager),
            $inputFilterPluginManager ?? new InputFilterPluginManager($serviceManager)
        );
        $serviceManager->setService(Factory::class, $factory);

        return $factory;
    }

    private function createInputFilterPluginManagerMockForPlugin(
        string $pluginName,
        mixed $pluginValue,
    ): InputFilterPluginManager {
        $pluginManager = $this->createMock(InputFilterPluginManager::class);
        $pluginManager->expects(self::atLeastOnce())
            ->method('has')
            ->with($pluginName)
            ->willReturn(true);
        $pluginManager->expects(self::atLeastOnce())
            ->method('get')
            ->with($pluginName)
            ->willReturn($pluginValue);
        return $pluginManager;
    }
}
