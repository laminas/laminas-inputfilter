<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\InitializableInterface;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @covers Laminas\InputFilter\InputFilterPluginManager
 */
class InputFilterPluginManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InputFilterPluginManager
     */
    protected $manager;

    /**
     * @var ServiceManager
     */
    protected $services;

    public function setUp()
    {
        $this->services = new ServiceManager();
        $this->manager = new InputFilterPluginManager($this->services);
    }

    public function testIsASubclassOfAbstractPluginManager()
    {
        $this->assertInstanceOf(AbstractPluginManager::class, $this->manager);
    }

    public function testIsNotSharedByDefault()
    {
        $property = method_exists($this->manager, 'configure')
            ? 'sharedByDefault' // v3
            : 'shareByDefault'; // v2

        $this->assertAttributeSame(false, $property, $this->manager);
    }

    public function testRegisteringInvalidElementRaisesException()
    {
        $this->setExpectedException(
            $this->getServiceNotFoundException(),
            'must implement Laminas\InputFilter\InputFilterInterface or Laminas\InputFilter\InputInterface'
        );
        $this->manager->setService('test', $this);
    }

    public function testLoadingInvalidElementRaisesException()
    {
        $this->manager->setInvokableClass('test', get_class($this));
        $this->setExpectedException($this->getServiceNotFoundException());
        $this->manager->get('test');
    }

    public function defaultInvokableClassesProvider()
    {
        return [
            // Description => [$alias, $expectedInstance]
            'inputfilter' => ['inputfilter', InputFilter::class],
            'collection' => ['collection', CollectionInputFilter::class],
        ];
    }

    /**
     * @dataProvider defaultInvokableClassesProvider
     */
    public function testDefaultInvokableClasses($alias, $expectedInstance)
    {
        $service = $this->manager->get($alias);

        $this->assertInstanceOf($expectedInstance, $service, 'get() return type not match');
    }

    public function testInputFilterInvokableClassSMDependenciesArePopulatedWithoutServiceLocator()
    {
        /** @var InputFilter $service */
        $service = $this->manager->get('inputfilter');

        $factory = $service->getFactory();
        $this->assertSame(
            $this->manager,
            $factory->getInputFilterManager(),
            'Factory::getInputFilterManager() is not populated with the expected plugin manager'
        );
    }

    public function testInputFilterInvokableClassSMDependenciesArePopulatedWithServiceLocator()
    {
        $filterManager = $this->getMockBuilder(FilterPluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $validatorManager = $this->getMockBuilder(ValidatorPluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->services->setService('FilterManager', $filterManager);
        $this->services->setService('ValidatorManager', $validatorManager);

        /** @var InputFilter $service */
        $service = $this->manager->get('inputfilter');

        $factory = $service->getFactory();
        $this->assertSame(
            $this->manager,
            $factory->getInputFilterManager(),
            'Factory::getInputFilterManager() is not populated with the expected plugin manager'
        );

        $defaultFilterChain = $factory->getDefaultFilterChain();
        $this->assertSame(
            $filterManager,
            $defaultFilterChain->getPluginManager(),
            'Factory::getDefaultFilterChain() is not populated with the expected plugin manager'
        );

        $defaultValidatorChain = $factory->getDefaultValidatorChain();
        $this->assertSame(
            $validatorManager,
            $defaultValidatorChain->getPluginManager(),
            'Factory::getDefaultValidatorChain() is not populated with the expected plugin manager'
        );
    }

    public function serviceProvider()
    {
        $inputFilterInterfaceMock = $this->createInputFilterInterfaceMock();
        $inputInterfaceMock = $this->createInputInterfaceMock();

        // @codingStandardsIgnoreStart
        return [
            // Description         => [$serviceName,                  $service,                  $instanceOf]
            'InputFilterInterface' => ['inputFilterInterfaceService', $inputFilterInterfaceMock, InputFilterInterface::class],
            'InputInterface'       => ['inputInterfaceService',       $inputInterfaceMock,       InputInterface::class],
        ];
        // @codingStandardsIgnoreEnd
    }

    /**
     * @dataProvider serviceProvider
     */
    public function testGet($serviceName, $service)
    {
        $this->manager->setService($serviceName, $service);

        $this->assertSame($service, $this->manager->get($serviceName), 'get() value not match');
    }

    /**
     * @dataProvider serviceProvider
     */
    public function testServicesAreInitiatedIfImplementsInitializableInterface($serviceName, $service, $instanceOf)
    {
        $initializableProphecy = $this->prophesize($instanceOf)->willImplement(InitializableInterface::class);
        $service = $initializableProphecy->reveal();

        $this->manager->setService($serviceName, $service);
        $this->assertSame($service, $this->manager->get($serviceName), 'get() value not match');

        /** @noinspection PhpUndefinedMethodInspection */
        $initializableProphecy->init()->shouldBeCalled();
    }

    public function testPopulateFactoryCanAcceptInputFilterAsFirstArgumentAndWillUseFactoryWhenItDoes()
    {
        $inputFilter = new InputFilter();
        $this->manager->populateFactory($inputFilter);

        $this->assertSame($this->manager, $inputFilter->getFactory()->getInputFilterManager());
    }

    /**
     * @return MockObject|InputFilterInterface
     */
    protected function createInputFilterInterfaceMock()
    {
        /** @var InputFilterInterface|MockObject $inputFilter */
        $inputFilter = $this->getMock(InputFilterInterface::class);

        return $inputFilter;
    }

    /**
     * @return MockObject|InputInterface
     */
    protected function createInputInterfaceMock()
    {
        /** @var InputInterface|MockObject $input */
        $input = $this->getMock(InputInterface::class);

        return $input;
    }

    /**
     * @return MockObject|ServiceLocatorInterface
     */
    protected function createServiceLocatorInterfaceMock()
    {
        /** @var ServiceLocatorInterface|MockObject $serviceLocator */
        $serviceLocator = $this->getMock(ServiceLocatorInterface::class);

        return $serviceLocator;
    }

    protected function getServiceNotFoundException()
    {
        if (method_exists($this->manager, 'configure')) {
            return InvalidServiceException::class;
        }
        return RuntimeException::class;
    }
}
