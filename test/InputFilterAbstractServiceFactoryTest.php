<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator;
use Laminas\Validator\ValidatorInterface;
use Laminas\Validator\ValidatorPluginManager;
use LaminasTest\InputFilter\TestAsset\Foo;
use PHPUnit\Framework\TestCase;

use function call_user_func_array;
use function count;

/**
 * @covers \Laminas\InputFilter\InputFilterAbstractServiceFactory
 */
class InputFilterAbstractServiceFactoryTest extends TestCase
{
    /** @var ServiceManager */
    protected $services;

    /** @var InputFilterPluginManager */
    protected $filters;

    /** @var InputFilterAbstractServiceFactory */
    protected $factory;

    protected function setUp(): void
    {
        $this->services = new ServiceManager();
        $this->filters  = new InputFilterPluginManager($this->services);
        $this->services->setService(InputFilterPluginManager::class, $this->filters);

        $this->factory = new InputFilterAbstractServiceFactory();
    }

    public function testCannotCreateServiceIfNoConfigServicePresent(): void
    {
        $method = 'canCreate';
        $args   = [$this->services, 'filter'];
        $this->assertFalse(call_user_func_array([$this->factory, $method], $args));
    }

    public function testCannotCreateServiceIfConfigServiceDoesNotHaveInputFiltersConfiguration(): void
    {
        $this->services->setService('config', []);
        $method = 'canCreate';
        $args   = [$this->services, 'filter'];

        $this->assertFalse(call_user_func_array([$this->factory, $method], $args));
    }

    public function testCannotCreateServiceIfConfigInputFiltersDoesNotContainMatchingServiceName(): void
    {
        $this->services->setService('config', [
            'input_filter_specs' => [],
        ]);
        $method = 'canCreate';
        $args   = [$this->services, 'filter'];
        $this->assertFalse(call_user_func_array([$this->factory, $method], $args));
    }

    public function testCanCreateServiceIfConfigInputFiltersContainsMatchingServiceName(): void
    {
        $this->services->setService('config', [
            'input_filter_specs' => [
                'filter' => [],
            ],
        ]);
        $method = 'canCreate';
        $args   = [$this->services, 'filter'];
        $this->assertTrue(call_user_func_array([$this->factory, $method], $args));
    }

    public function testCreatesInputFilterInstance(): void
    {
        $this->services->setService('config', [
            'input_filter_specs' => [
                'filter' => [],
            ],
        ]);
        $method = '__invoke';
        $args   = [$this->services, 'filter'];
        $filter = call_user_func_array([$this->factory, $method], $args);
        $this->assertInstanceOf(InputFilterInterface::class, $filter);
    }

    /**
     * @depends testCreatesInputFilterInstance
     */
    public function testUsesConfiguredValidationAndFilterManagerServicesWhenCreatingInputFilter(): void
    {
        $filters = new FilterPluginManager($this->services);
        $filter  = function () {
        };
        $filters->setService('foo', $filter);

        $validators = new ValidatorPluginManager($this->services);
        $validator  = $this->createMock(ValidatorInterface::class);
        $validators->setService('foo', $validator);

        $this->services->setService(FilterPluginManager::class, $filters);
        $this->services->setService(ValidatorPluginManager::class, $validators);
        $this->services->setService('config', [
            'input_filter_specs' => [
                'filter' => [
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

        $method      = '__invoke';
        $args        = [$this->services, 'filter'];
        $inputFilter = call_user_func_array([$this->factory, $method], $args);
        $this->assertTrue($inputFilter->has('input'));

        $input = $inputFilter->get('input');

        $filterChain = $input->getFilterChain();
        $this->assertSame($filters, $filterChain->getPluginManager());
        $this->assertEquals(1, count($filterChain));
        $this->assertSame($filter, $filterChain->plugin('foo'));
        $this->assertEquals(1, count($filterChain));

        $validatorChain = $input->getValidatorChain();
        $this->assertSame($validators, $validatorChain->getPluginManager());
        $this->assertEquals(1, count($validatorChain));
        $this->assertSame($validator, $validatorChain->plugin('foo'));
        $this->assertEquals(1, count($validatorChain));
    }

    public function testRetrieveInputFilterFromInputFilterPluginManager(): void
    {
        $this->services->setService('config', [
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
        $validators = new ValidatorPluginManager($this->services);
        $validator  = $this->createMock(ValidatorInterface::class);
        $this->services->setService(ValidatorPluginManager::class, $validators);
        $validators->setService('foo', $validator);

        $filters = new FilterPluginManager($this->services);
        $filter  = static function (): void {
        };
        $filters->setService('foo', $filter);

        $this->services->setService(FilterPluginManager::class, $filters);
        $this->services->get(InputFilterPluginManager::class)
            ->addAbstractFactory(InputFilterAbstractServiceFactory::class);

        $inputFilter = $this->services->get(InputFilterPluginManager::class)->get('foobar');
        $this->assertInstanceOf(InputFilterInterface::class, $inputFilter);
    }

    /**
     * @depends testCreatesInputFilterInstance
     */
    public function testInjectsInputFilterManagerFromServiceManager(): void
    {
        $this->services->setService('config', [
            'input_filter_specs' => [
                'filter' => [],
            ],
        ]);
        $this->filters->addAbstractFactory(TestAsset\FooAbstractFactory::class);
        $filter             = $this->factory->__invoke($this->services, 'filter');
        $inputFilterManager = $filter->getFactory()->getInputFilterManager();

        $this->assertInstanceOf(InputFilterPluginManager::class, $inputFilterManager);
        $this->assertInstanceOf(Foo::class, $inputFilterManager->get('foo'));
    }

    public function testAllowsPassingNonPluginManagerContainerToFactoryWithServiceManagerV2(): void
    {
        $this->services->setService('config', [
            'input_filter_specs' => [
                'filter' => [],
            ],
        ]);
        $canCreate = 'canCreate';
        $create    = '__invoke';
        $args      = [$this->services, 'filter'];
        $this->assertTrue(call_user_func_array([$this->factory, $canCreate], $args));
        $inputFilter = call_user_func_array([$this->factory, $create], $args);
        $this->assertInstanceOf(InputFilterInterface::class, $inputFilter);
    }

    /**
     * @see https://github.com/zendframework/zend-inputfilter/issues/155
     */
    public function testWillUseCustomFiltersWhenProvided(): void
    {
        $filter = $this->createMock(Filter\FilterInterface::class);

        $filters = new FilterPluginManager($this->services);
        $filters->setService('CustomFilter', $filter);

        $validators = new ValidatorPluginManager($this->services);

        $this->services->setService(FilterPluginManager::class, $filters);
        $this->services->setService(ValidatorPluginManager::class, $validators);

        $this->services->setService('config', [
            'input_filter_specs' => [
                'test' => [
                    [
                        'name'       => 'a-file-element',
                        'type'       => FileInput::class,
                        'required'   => true,
                        'validators' => [
                            [
                                'name'    => Validator\File\UploadFile::class,
                                'options' => [
                                    'breakchainonfailure' => true,
                                ],
                            ],
                            [
                                'name'    => Validator\File\Size::class,
                                'options' => [
                                    'breakchainonfailure' => true,
                                    'max'                 => '6GB',
                                ],
                            ],
                            [
                                'name'    => Validator\File\Extension::class,
                                'options' => [
                                    'breakchainonfailure' => true,
                                    'extension'           => 'csv,zip',
                                ],
                            ],
                        ],
                        'filters'    => [
                            ['name' => 'CustomFilter'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->services->get(InputFilterPluginManager::class)
            ->addAbstractFactory(InputFilterAbstractServiceFactory::class);

        $inputFilter = $this->services->get(InputFilterPluginManager::class)->get('test');
        $this->assertInstanceOf(InputFilterInterface::class, $inputFilter);

        $input = $inputFilter->get('a-file-element');
        $this->assertInstanceOf(FileInput::class, $input);

        $filters = $input->getFilterChain();
        $this->assertCount(1, $filters);

        $callback = $filters->getFilters()->top();
        $this->assertIsArray($callback);
        $this->assertSame($filter, $callback[0]);
    }
}
