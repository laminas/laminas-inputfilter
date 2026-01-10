<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterInterface;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use Laminas\Validator\ValidatorPluginManager;
use LaminasTest\InputFilter\TestAsset\Foo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use ReflectionProperty;

use function assert;
use function is_string;
use function iterator_to_array;
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
    public function testCanCreate(array|null $config, bool $expectedResult): void
    {
        $services = new ServiceManager($config === null ? [] : ['services' => ['config' => $config]]);

        self::assertEquals($expectedResult, $this->factory->canCreate($services, 'filter'));
    }

    /** @return array<string, array{0: array|null, 1: bool}> */
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

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    #[Depends('testCreatesInputFilterInstance')]
    public function testUsesConfiguredValidationAndFilterManagerServicesWhenCreatingInputFilter(): void
    {
        $filter    = static function (): void {
        };
        $validator = new NotEmpty();

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
            'filters'            => [
                'services' => [
                    'foo_filter' => $filter,
                ],
            ],
            'validators'         => [
                'services' => [
                    'foo_validator' => $validator,
                ],
            ],
        ]);

        $inputFilter = $this->factory->__invoke($services, 'filter');
        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);
        self::assertTrue($inputFilter->has('input'));
        $input = $inputFilter->get('input');
        self::assertInstanceOf(InputInterface::class, $input);

        $filterChain = $input->getFilterChain();
        self::assertInstanceOf(FilterChain::class, $filterChain);
        $filterChain = iterator_to_array($filterChain, false);
        self::assertCount(1, $filterChain);
        self::assertSame($filter, $filterChain[0]);

        $validatorChain = $input->getValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $validatorChain);
        self::assertCount(1, $validatorChain);
        self::assertSame($validator, $validatorChain->plugin('foo_validator'));
    }

    public function testRetrieveInputFilterFromInputFilterPluginManager(): void
    {
        $filter    = static function (): void {
        };
        $validator = $this->createMock(ValidatorInterface::class);
        $services  = TestHelper::getContainer([
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
            'filters'            => [
                'services' => [
                    'foo' => $filter,
                ],
            ],
            'validators'         => [
                'services' => [
                    'foo' => $validator,
                ],
            ],
        ]);

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
        $filter = new /** @implements FilterInterface<mixed> */ class implements FilterInterface
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
            'filters'            => [
                'factories' => [
                    'CustomFilter' => static fn (): FilterInterface => $filter,
                ],
            ],
        ]);

        $inputFilter = $services->get(InputFilterPluginManager::class)->get('test');
        self::assertInstanceOf(InputFilterInterface::class, $inputFilter);

        $input = $inputFilter->get('value');
        self::assertInstanceOf(InputInterface::class, $input);

        $filters = $input->getFilterChain();
        self::assertSame('oof', $filters->filter('foo'));
    }
}
