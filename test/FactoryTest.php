<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter;
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
use Laminas\ServiceManager;
use Laminas\Validator;
use LaminasTest\InputFilter\TestAsset\CustomInput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function sprintf;

/**
 * @covers \Laminas\InputFilter\Factory
 */
class FactoryTest extends TestCase
{
    public function testCreateInputWithInvalidDataTypeThrowsInvalidArgumentException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expects an array or Traversable; received "string"');
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

        /** @psalm-suppress MixedArgumentTypeCoercion */
        $factory = new Factory($pluginManager);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Input factory expects the "type" to be a valid class or a plugin name; received "foo"'
        );
        $factory->createInput([
            'type' => $type,
        ]);
    }

    public function testGetInputFilterManagerSettedByItsSetter(): void
    {
        $pluginManager = $this->createMock(InputFilterPluginManager::class);
        $factory       = new Factory();
        /** @psalm-suppress MixedArgumentTypeCoercion */
        $factory->setInputFilterManager($pluginManager);
        self::assertSame($pluginManager, $factory->getInputFilterManager());
    }

    public function testGetInputFilterManagerWhenYouConstructFactoryWithIt(): void
    {
        $pluginManager = $this->createMock(InputFilterPluginManager::class);
        /** @psalm-suppress MixedArgumentTypeCoercion */
        $factory = new Factory($pluginManager);
        self::assertSame($pluginManager, $factory->getInputFilterManager());
    }

    public function testCreateInputWithTypeAsAnInvalidPluginInstanceThrowException(): void
    {
        $type          = 'fooPlugin';
        $pluginManager = $this->createInputFilterPluginManagerMockForPlugin($type, 'invalid_value');
        $factory       = new Factory($pluginManager);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Input factory expects the "type" to be a class implementing Laminas\InputFilter\InputInterface; '
            . 'received "fooPlugin"'
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
            'Input factory expects the "type" to be a class implementing Laminas\InputFilter\InputInterface; '
            . 'received "stdClass"'
        );
        $factory->createInput([
            'type' => $type,
        ]);
    }

    public function testCreateInputWithFiltersAsAnInvalidTypeThrowException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'expects the value associated with "filters" to be an array/Traversable'
            . ' of filters or filter specifications, or a FilterChain; received "string"'
        );
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
            'Invalid filter specification provided; was neither a filter instance nor an array specification'
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'expects the value associated with "validators" to be an array/Traversable of validators or validator '
            . 'specifications, or a ValidatorChain; received "string"'
        );
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

    /** @psalm-return array<string, array{0: 'continue_if_empty'|'fallback_value'}> */
    public function inputTypeSpecificationProvider(): array
    {
        return [
            // Description => [$specificationKey]
            'continue_if_empty' => ['continue_if_empty'],
            'fallback_value'    => ['fallback_value'],
        ];
    }

    /**
     * @dataProvider inputTypeSpecificationProvider
     * @psalm-param 'continue_if_empty'|'fallback_value' $specificationKey
     */
    public function testCreateInputWithSpecificInputTypeSettingsThrowException(string $specificationKey): void
    {
        $factory = $this->createDefaultFactory();
        $type    = 'pluginInputInterface';

        $pluginManager = $this->createInputFilterPluginManagerMockForPlugin(
            $type,
            $this->createMock(InputInterface::class)
        );
        $factory->setInputFilterManager($pluginManager);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf('"%s" can only set to inputs of type "Laminas\InputFilter\Input"', $specificationKey)
        );
        /** @psalm-suppress ArgumentTypeCoercion */
        $factory->createInput([
            'type'            => $type,
            $specificationKey => true,
        ]);
    }

    public function testCreateInputWithValidatorsAsAnCollectionOfInvalidTypesThrowException(): void
    {
        $factory = $this->createDefaultFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid validator specification provided; was neither a validator instance nor an array specification'
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

    public function testFactoryComposesFilterChainByDefault(): void
    {
        $factory = $this->createDefaultFactory();
        self::assertInstanceOf(Filter\FilterChain::class, $factory->getDefaultFilterChain());
    }

    public function testFactoryComposesValidatorChainByDefault(): void
    {
        $factory = $this->createDefaultFactory();
        self::assertInstanceOf(Validator\ValidatorChain::class, $factory->getDefaultValidatorChain());
    }

    public function testFactoryAllowsInjectingFilterChain(): void
    {
        $factory     = $this->createDefaultFactory();
        $filterChain = new Filter\FilterChain();
        $factory->setDefaultFilterChain($filterChain);
        self::assertSame($filterChain, $factory->getDefaultFilterChain());
    }

    public function testFactoryAllowsInjectingValidatorChain(): void
    {
        $factory        = $this->createDefaultFactory();
        $validatorChain = new Validator\ValidatorChain();
        $factory->setDefaultValidatorChain($validatorChain);
        self::assertSame($validatorChain, $factory->getDefaultValidatorChain());
    }

    public function testFactoryUsesComposedFilterChainWhenCreatingNewInputObjects(): void
    {
        $smMock = $this->createMock(ContainerInterface::class);

        $factory       = $this->createDefaultFactory();
        $filterChain   = new Filter\FilterChain();
        $pluginManager = new Filter\FilterPluginManager($smMock);
        $filterChain->setPluginManager($pluginManager);
        $factory->setDefaultFilterChain($filterChain);
        $input = $factory->createInput([
            'name' => 'foo',
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        $inputFilterChain = $input->getFilterChain();
        self::assertNotSame($filterChain, $inputFilterChain);
        self::assertSame($pluginManager, $inputFilterChain->getPluginManager());
    }

    public function testFactoryUsesComposedValidatorChainWhenCreatingNewInputObjects(): void
    {
        $smMock           = $this->createMock(ContainerInterface::class);
        $factory          = $this->createDefaultFactory();
        $validatorChain   = new Validator\ValidatorChain();
        $validatorPlugins = new Validator\ValidatorPluginManager($smMock);
        $validatorChain->setPluginManager($validatorPlugins);
        $factory->setDefaultValidatorChain($validatorChain);
        $input = $factory->createInput([
            'name' => 'foo',
        ]);
        self::assertInstanceOf(InputInterface::class, $input);
        $inputValidatorChain = $input->getValidatorChain();
        self::assertNotSame($validatorChain, $inputValidatorChain);
        self::assertSame($validatorPlugins, $inputValidatorChain->getPluginManager());
    }

    public function testFactoryInjectsComposedFilterAndValidatorChainsIntoInputObjectsWhenCreatingNewInputFilterObjects(): void // phpcs:ignore
    {
        $smMock           = $this->createMock(ContainerInterface::class);
        $factory          = $this->createDefaultFactory();
        $filterPlugins    = new Filter\FilterPluginManager($smMock);
        $validatorPlugins = new Validator\ValidatorPluginManager($smMock);
        $filterChain      = new Filter\FilterChain();
        $validatorChain   = new Validator\ValidatorChain();
        $filterChain->setPluginManager($filterPlugins);
        $validatorChain->setPluginManager($validatorPlugins);
        $factory->setDefaultFilterChain($filterChain);
        $factory->setDefaultValidatorChain($validatorChain);

        $inputFilter = $factory->createInputFilter([
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
            switch ($index) {
                case 0:
                    self::assertInstanceOf(Filter\StringTrim::class, $filter);
                    break;
                case 1:
                    self::assertSame($htmlEntities, $filter);
                    break;
                case 2:
                    self::assertInstanceOf(Filter\StringToLower::class, $filter);
                    self::assertEquals('ISO-8859-1', $filter->getEncoding());
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
                    self::assertEquals(3, $validator->getMin());
                    self::assertEquals(5, $validator->getMax());
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
        $input   = new Input();

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
        $input   = new InputFilter();

        $inputFilter = $factory->createInputFilter([
            'foo' => $input,
        ]);

        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);
        self::assertTrue($inputFilter->has('foo'));
        self::assertEquals($input, $inputFilter->get('foo'));
    }

    public function testFactoryWillCreateInputFilterAndAllInputObjectsFromGivenConfiguration(): void
    {
        $factory     = $this->createDefaultFactory();
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
            'inputfilter' => new InputFilter(),
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

    public function testCustomFactoryInCollection(): void
    {
        $factory = new TestAsset\CustomFactory();
        /** @var CollectionInputFilter $inputFilter */
        $inputFilter = $factory->createInputFilter([
            'type'         => 'collection',
            'input_filter' => new InputFilter(),
        ]);
        self::assertInstanceOf(TestAsset\CustomFactory::class, $inputFilter->getFactory());
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

    public function testSetInputFilterManagerWithServiceManager(): void
    {
        $serviceManager         = new ServiceManager\ServiceManager();
        $inputFilterManager     = new InputFilterPluginManager($serviceManager);
        $validatorPluginManager = new Validator\ValidatorPluginManager($serviceManager);
        $filterPluginManager    = new Filter\FilterPluginManager($serviceManager);
        $serviceManager->setService(Validator\ValidatorPluginManager::class, $validatorPluginManager);
        $serviceManager->setService(Filter\FilterPluginManager::class, $filterPluginManager);
        $factory = new Factory($inputFilterManager);

        self::assertSame($validatorPluginManager, $factory->getDefaultValidatorChain()->getPluginManager());
        self::assertSame($filterPluginManager, $factory->getDefaultFilterChain()->getPluginManager());
    }

    public function testSetInputFilterManagerWithoutServiceManager(): void
    {
        $smMock             = $this->createMock(ContainerInterface::class);
        $inputFilterManager = new InputFilterPluginManager($smMock);
        $factory            = new Factory($inputFilterManager);
        self::assertSame($inputFilterManager, $factory->getInputFilterManager());
    }

    public function testSetInputFilterManagerOnConstruct(): void
    {
        $smMock             = $this->createMock(ContainerInterface::class);
        $inputFilterManager = new InputFilterPluginManager($smMock);
        $factory            = new Factory($inputFilterManager);
        self::assertSame($inputFilterManager, $factory->getInputFilterManager());
    }

    /**
     * @covers \Laminas\InputFilter\Factory::createInput
     */
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
        $serviceManager = new ServiceManager\ServiceManager();
        $pluginManager  = new InputFilterPluginManager($serviceManager);
        $pluginManager->setService('bar', new Input('bar'));
        $factory = new Factory($pluginManager);

        $input = $factory->createInput([
            'type' => 'bar',
        ]);

        self::assertInstanceOf(InputInterface::class, $input);
        self::assertSame('bar', $input->getName());
    }

    public function testInputFromPluginManagerMayBeFurtherConfiguredWithSpec(): void
    {
        $pluginManager = new InputFilterPluginManager(new ServiceManager\ServiceManager());
        $pluginManager->setService('bar', $barInput = new Input('bar'));
        $factory = new Factory($pluginManager);
        self::assertTrue($barInput->isRequired());
        $factory->setInputFilterManager($pluginManager);

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

    public function testClearDefaultFilterChain(): void
    {
        $factory = $this->createDefaultFactory();
        $factory->clearDefaultFilterChain();
        self::assertNull($factory->getDefaultFilterChain());
    }

    public function testClearDefaultValidatorChain(): void
    {
        $factory = $this->createDefaultFactory();
        $factory->clearDefaultValidatorChain();
        self::assertNull($factory->getDefaultValidatorChain());
    }

    public function testWhenCreateInputPullsInputFromThePluginManagerItMustNotOverwriteFilterAndValidatorChains(): void
    {
        $input          = new Input();
        $filterChain    = new Filter\FilterChain();
        $validatorChain = new Validator\ValidatorChain();
        $input->setFilterChain($filterChain);
        $input->setValidatorChain($validatorChain);

        $pluginManager = new InputFilterPluginManager(new ServiceManager\ServiceManager());
        $pluginManager->setService('Some\Test\Input', $input);

        $factory               = new Factory($pluginManager);
        $defaultFilterChain    = new Filter\FilterChain();
        $defaultValidatorChain = new Validator\ValidatorChain();
        $factory->setDefaultFilterChain($defaultFilterChain);
        $factory->setDefaultValidatorChain($defaultValidatorChain);

        $spec         = ['type' => 'Some\Test\Input'];
        $createdInput = $factory->createInput($spec);

        self::assertSame($input, $createdInput);
        self::assertSame($filterChain, $input->getFilterChain());
        self::assertSame($validatorChain, $input->getValidatorChain());
        self::assertNotSame($defaultFilterChain, $input->getFilterChain());
        self::assertNotSame($defaultValidatorChain, $input->getValidatorChain());
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
            'inputfilter'      => new InputFilter(),
            'count'            => 3,
        ]);

        self::assertInstanceOf(CollectionInputFilter::class, $inputFilter);

        $notEmptyValidator = $inputFilter->getNotEmptyValidator();
        $messageTemplates  = $notEmptyValidator->getMessageTemplates();
        self::assertArrayHasKey(Validator\NotEmpty::IS_EMPTY, $messageTemplates);
        self::assertSame($message, $messageTemplates[Validator\NotEmpty::IS_EMPTY]);
    }

    protected function createDefaultFactory(): Factory
    {
        return new Factory();
    }

    /**
     * @param mixed $pluginValue
     */
    protected function createInputFilterPluginManagerMockForPlugin(
        string $pluginName,
        $pluginValue
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
