<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
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

    public function setUp()
    {
        $this->manager = new InputFilterPluginManager();
    }

    public function testIsASubclassOfAbstractPluginManager()
    {
        $this->assertInstanceOf('Laminas\ServiceManager\AbstractPluginManager', $this->manager);
    }

    public function testIsNotSharedByDefault()
    {
        $this->assertFalse($this->manager->shareByDefault());
    }

    public function testRegisteringInvalidElementRaisesException()
    {
        $this->setExpectedException(
            'Laminas\InputFilter\Exception\RuntimeException',
            'must implement Laminas\InputFilter\InputFilterInterface or Laminas\InputFilter\InputInterface'
        );
        $this->manager->setService('test', $this);
    }

    public function testLoadingInvalidElementRaisesException()
    {
        $this->manager->setInvokableClass('test', get_class($this));
        $this->setExpectedException('Laminas\InputFilter\Exception\RuntimeException');
        $this->manager->get('test');
    }

    public function defaultInvokableClassesProvider()
    {
        return array(
            // Description => [$alias, $expectedInstance]
            'inputfilter' => array('inputfilter', 'Laminas\InputFilter\InputFilter'),
            'collection' => array('collection', 'Laminas\InputFilter\CollectionInputFilter'),
        );
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
        $this->assertNull($this->manager->getServiceLocator(), 'Plugin manager is expected to no have a service locator');

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
        $filterManager = $this->getMock('Laminas\Filter\FilterPluginManager');
        $validatorManager = $this->getMock('Laminas\Validator\ValidatorPluginManager');

        $serviceLocator = $this->createServiceLocatorInterfaceMock();
        $serviceLocator->method('get')
            ->willReturnMap(
                array(
                    array('FilterManager', $filterManager),
                    array('ValidatorManager', $validatorManager),
                )
            )
        ;

        $this->manager->setServiceLocator($serviceLocator);
        $this->assertSame($serviceLocator, $this->manager->getServiceLocator(), 'getServiceLocator() value not match');

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

        // @formatter:off
        return array(
            // Description => [$serviceName, $service, $instanceOf]
            'InputFilterInterface' => array('inputFilterInterfaceService', $inputFilterInterfaceMock, 'Laminas\InputFilter\InputFilterInterface'),
            'InputInterface' => array('inputInterfaceService', $inputInterfaceMock, 'Laminas\InputFilter\InputInterface'),
        );
        // @formatter:on
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
        $initializableProphecy = $this->prophesize($instanceOf)->willImplement('Laminas\Stdlib\InitializableInterface');
        $service = $initializableProphecy->reveal();

        $this->manager->setService($serviceName, $service);
        $this->assertSame($service, $this->manager->get($serviceName), 'get() value not match');

        /** @noinspection PhpUndefinedMethodInspection */
        $initializableProphecy->init()->shouldBeCalled();
    }

    /**
     * @return MockObject|InputFilterInterface
     */
    protected function createInputFilterInterfaceMock()
    {
        /** @var InputFilterInterface|MockObject $inputFilter */
        $inputFilter = $this->getMock('Laminas\InputFilter\InputFilterInterface');

        return $inputFilter;
    }

    /**
     * @return MockObject|InputInterface
     */
    protected function createInputInterfaceMock()
    {
        /** @var InputInterface|MockObject $input */
        $input = $this->getMock('Laminas\InputFilter\InputInterface');

        return $input;
    }

    /**
     * @return MockObject|ServiceLocatorInterface
     */
    protected function createServiceLocatorInterfaceMock()
    {
        /** @var ServiceLocatorInterface|MockObject $serviceLocator */
        $serviceLocator = $this->getMock('Laminas\ServiceManager\ServiceLocatorInterface');

        return $serviceLocator;
    }
}
