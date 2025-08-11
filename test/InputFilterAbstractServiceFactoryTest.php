<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter;
use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use Laminas\Validator\ValidatorPluginManager;
use LaminasTest\InputFilter\TestAsset\Foo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function assert;
use function is_string;
use function strrev;

#[CoversClass(InputFilterAbstractServiceFactory::class)]
final class InputFilterAbstractServiceFactoryTest extends TestCase
{
    private ServiceManager $services;
    private InputFilterPluginManager $filters;
    private InputFilterAbstractServiceFactory $factory;
    private FilterPluginManager $filterPluginManager;
    private ValidatorPluginManager $validatorPluginManager;

    protected function setUp(): void
    {
        $this->services = TestHelper::getContainer();
        $this->filters  = new InputFilterPluginManager($this->services);
        $this->services->setService(InputFilterPluginManager::class, $this->filters);

        $this->factory = new InputFilterAbstractServiceFactory();

        $this->filterPluginManager = new FilterPluginManager($this->services);
        $this->services->setService(FilterPluginManager::class, $this->filterPluginManager);

        $this->validatorPluginManager = new ValidatorPluginManager($this->services);
        $this->services->setService(ValidatorPluginManager::class, $this->validatorPluginManager);

        $factory = new Factory($this->filterPluginManager, $this->validatorPluginManager, $this->filters);
        $this->services->setService(Factory::class, $factory);
    }

    #[DataProvider('canCreateProvider')]
    public function testCanCreate(?array $config = null, bool $expectedResult): void
    {
        $services = new ServiceManager();

        if ($config !== null) {
            $services->setService('config', $config);
        }

        self::assertEquals($expectedResult, $this->factory->canCreate($services, 'filter'));
    }

    public static function canCreateProvider(): array
    {
        return [
            'No config service present'                                => [null, false],
            'Config service does not have Input filters configuration' => [[], false],
            'Config service does not contain matching service name'    => [
                ['input_filter_specs' => []],
                false,
            ],
            'Config service contains matching service name'            => [
                ['input_filter_specs' => ['filter' => []]],
                true,
            ],
        ];
    }

    public function testCreatesInputFilterInstance(): void
    {
        $services = TestHelper::getContainer([
            'input_filter_specs' => [
                'filter' => [],
            ],
        ]);

        $filter = $this->factory->__invoke($services, 'filter');
        self::assertInstanceOf(InputFilterInterface::class, $filter);
    }

    #[Depends('testCreatesInputFilterInstance')]
    public function testUsesConfiguredValidationAndFilterManagerServicesWhenCreatingInputFilter(): void
    {
        $services = TestHelper::getContainer([
            'input_filter_specs' => [
                'filter' => [
                    'input' => [
                        'name'       => 'input',
                        'required'   => true,
                        'filters'    => [
                            ['name' => 'foo_filter'],
                        ],
                        'validators' => [
                            ['name' => 'foo_validator'],
                        ],
                    ],
                ],
            ],
        ]);

        $filterPluginManager = $services->get(FilterPluginManager::class);
        $filter              = static function (): void {
        };
        $filterPluginManager->configure(['services' => ['foo_filter' => $filter]]);

        $validatorPluginManager = $services->get(ValidatorPluginManager::class);

        $validator = $this->createMock(ValidatorInterface::class);
        $validatorPluginManager->configure(['services' => ['foo_validator' => $validator]]);

        // Temporary assertions to check configuration
        self::assertSame($filter, $filterPluginManager->get('foo_filter'));
        self::assertSame($validator, $validatorPluginManager->get('foo_validator'));
        // End

        $inputFilter = $this->factory->__invoke($services, 'filter');
        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);
        self::assertTrue($inputFilter->has('input'));

        $input = $inputFilter->get('input');
        self::assertInstanceOf(InputInterface::class, $input);

        $filterChain = $input->getFilterChain();
        self::assertInstanceOf(FilterChain::class, $filterChain);
//        self::assertSame($this->filterPluginManager, $filterChain->getPluginManager());
        self::assertCount(1, $filterChain);
//        self::assertSame($filter, $filterChain->plugin('foo'));
        self::assertCount(1, $filterChain);

        $validatorChain = $input->getValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $validatorChain);
        self::assertSame($validatorPluginManager, $validatorChain->getPluginManager());
        self::assertCount(1, $validatorChain);
        self::assertSame($validator, $validatorChain->plugin('foo_validator'));
        self::assertCount(1, $validatorChain);
    }

    public function testRetrieveInputFilterFromInputFilterPluginManager(): void
    {
        $services = TestHelper::getContainer([
            'input_filter_specs' => [
                'foobar' => [
                    'input' => [
                        'name'       => 'input',
                        'required'   => true,
                        'filters'    => [
                            ['name' => 'foo'],
                        ],
                        'validators' => [
                            ['name' => 'foo'],
                        ],
                    ],
                ],
            ],
        ]);

        $validator = $this->createMock(ValidatorInterface::class);
        $this->validatorPluginManager->setService('foo', $validator);

        $filter = static function (): void {
        };
        $this->filterPluginManager->setService('foo', $filter);

        $this->services->get(InputFilterPluginManager::class)
            ->addAbstractFactory(InputFilterAbstractServiceFactory::class);

        $inputFilter = $services->get(InputFilterPluginManager::class)->get('foobar');
        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);
    }

    #[Depends('testCreatesInputFilterInstance')]
    public function testInjectsInputFilterManagerFromServiceManager(): void
    {
        $services = TestHelper::getContainer([
            'input_filter_specs' => [
                'filter' => [],
            ],
        ]);
        $filters  = $services->get(InputFilterPluginManager::class);
        $filters->configure(['abstract_factories' => [TestAsset\FooAbstractFactory::class]]);

        $filter = $this->factory->__invoke($services, 'filter');
        self::assertInstanceOf(InputFilter::class, $filter);

        /** @var Factory $factory */
        $factory            = (new ReflectionProperty($filter, 'factory'))->getValue($filter);
        $inputFilterManager = (new ReflectionProperty($factory, 'inputFilterPluginManager'))->getValue($factory);

        self::assertInstanceOf(InputFilterPluginManager::class, $inputFilterManager);
        self::assertInstanceOf(Foo::class, $inputFilterManager->get('foo'));
    }

    public function testAllowsPassingNonPluginManagerContainerToFactoryWithServiceManagerV2(): void
    {
        $services = TestHelper::getContainer([
            'input_filter_specs' => [
                'filter' => [],
            ],
        ]);
        self::assertTrue($this->factory->canCreate($services, 'filter'));

        $inputFilter = $this->factory->__invoke($services, 'filter');
        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);
    }

    /**
     * @see https://github.com/zendframework/zend-inputfilter/issues/155
     */
    public function testWillUseCustomFiltersWhenProvided(): void
    {
        $filter = new class implements Filter\FilterInterface
        {
            public function filter(mixed $value): string
            {
                assert(is_string($value));

                return strrev($value);
            }

            public function __invoke(mixed $value): string
            {
                return $this->filter($value);
            }
        };

        $services = TestHelper::getContainer([
            'input_filter_specs' => [
                'test' => [
                    [
                        'name'       => 'value',
                        'required'   => true,
                        'validators' => [],
                        'filters'    => [
                            ['name' => 'CustomFilter'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->filterPluginManager->configure(['services' => ['CustomFilter' => $filter]]);

        $services->get(InputFilterPluginManager::class)
            ->addAbstractFactory(InputFilterAbstractServiceFactory::class);

        $inputFilter = $services->get(InputFilterPluginManager::class)->get('test');
        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);

        $input = $inputFilter->get('value');
        self::assertInstanceOf(InputInterface::class, $input);

        $filters = $input->getFilterChain();
        self::assertCount(1, $filters);

        $callback = $filters->getIterator()->top();
        self::assertIsCallable($callback);
        self::assertSame('oof', $callback('foo'));
    }
}
