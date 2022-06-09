<?php

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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use ReflectionObject;

use function method_exists;

/**
 * @covers \Laminas\InputFilter\InputFilterPluginManager
 */
class InputFilterPluginManagerTest extends TestCase
{
    use ProphecyTrait;

    /** @var InputFilterPluginManager */
    protected $manager;

    /** @var ServiceManager */
    protected $services;

    protected function setUp(): void
    {
        $this->services = new ServiceManager();
        $this->manager  = new InputFilterPluginManager($this->services);
    }

    public function testIsASubclassOfAbstractPluginManager(): void
    {
        $this->assertInstanceOf(AbstractPluginManager::class, $this->manager);
    }

    public function testIsNotSharedByDefault(): void
    {
        $r = new ReflectionObject($this->manager);
        $p = $r->getProperty('sharedByDefault');
        $p->setAccessible(true);
        $this->assertFalse($p->getValue($this->manager));
    }

    public function testRegisteringInvalidElementRaisesException(): void
    {
        $this->expectException($this->getServiceNotFoundException());
        $this->expectExceptionMessage(
            'must implement Laminas\InputFilter\InputFilterInterface or Laminas\InputFilter\InputInterface'
        );
        $this->manager->setService('test', $this);
    }

    public function testLoadingInvalidElementRaisesException(): void
    {
        $this->manager->setInvokableClass('test', static::class);
        $this->expectException($this->getServiceNotFoundException());
        $this->manager->get('test');
    }

    /** @psalm-return array<string, array{0: string, 1: class-string<InputFilter>}> */
    public function defaultInvokableClassesProvider(): array
    {
        return [
            // Description => [$alias, $expectedInstance]
            'inputfilter' => ['inputfilter', InputFilter::class],
            'collection'  => ['collection', CollectionInputFilter::class],
        ];
    }

    /**
     * @dataProvider defaultInvokableClassesProvider
     * @psalm-param class-string $expectedInstance
     */
    public function testDefaultInvokableClasses(string $alias, string $expectedInstance): void
    {
        /** @var object $service */
        $service = $this->manager->get($alias);

        $this->assertInstanceOf($expectedInstance, $service, 'get() return type not match');
    }

    public function testInputFilterInvokableClassSMDependenciesArePopulatedWithoutServiceLocator(): void
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

    public function testInputFilterInvokableClassSMDependenciesArePopulatedWithServiceLocator(): void
    {
        $filterManager    = $this->getMockBuilder(FilterPluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $validatorManager = $this->getMockBuilder(ValidatorPluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->services->setService(FilterPluginManager::class, $filterManager);
        $this->services->setService(ValidatorPluginManager::class, $validatorManager);

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

    /**
     * @psalm-return array<string, array{
     *     0: string,
     *     1: InputInterface,
     *     2: class-string<InputInterface>
     * }>
     */
    public function serviceProvider(): array
    {
        $inputFilterInterfaceMock = $this->createInputFilterInterfaceMock();
        $inputInterfaceMock       = $this->createInputInterfaceMock();

        // phpcs:disable
        return [
            // Description         => [$serviceName,                  $service,                  $instanceOf]
            'InputFilterInterface' => ['inputFilterInterfaceService', $inputFilterInterfaceMock, InputFilterInterface::class],
            'InputInterface'       => ['inputInterfaceService',       $inputInterfaceMock,       InputInterface::class],
        ];
        // phpcs:enable
    }

    /**
     * @dataProvider serviceProvider
     */
    public function testGet(string $serviceName, object $service): void
    {
        $this->manager->setService($serviceName, $service);

        $this->assertSame($service, $this->manager->get($serviceName), 'get() value not match');
    }

    /**
     * @dataProvider serviceProvider
     * @param class-string<InputInterface> $instanceOf
     */
    public function testServicesAreInitiatedIfImplementsInitializableInterface(
        string $serviceName,
        object $service,
        string $instanceOf
    ) {
        $initializableProphecy = $this->prophesize($instanceOf)->willImplement(InitializableInterface::class);
        $service               = $initializableProphecy->reveal();

        $this->manager->setService($serviceName, $service);
        $this->assertSame($service, $this->manager->get($serviceName), 'get() value not match');

        /** @noinspection PhpUndefinedMethodInspection */
        $initializableProphecy->init()->shouldBeCalled();
    }

    public function testPopulateFactoryCanAcceptInputFilterAsFirstArgumentAndWillUseFactoryWhenItDoes(): void
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
        $inputFilter = $this->createMock(InputFilterInterface::class);

        return $inputFilter;
    }

    /**
     * @return MockObject|InputInterface
     */
    protected function createInputInterfaceMock()
    {
        /** @var InputInterface|MockObject $input */
        $input = $this->createMock(InputInterface::class);

        return $input;
    }

    /**
     * @return MockObject|ServiceLocatorInterface
     */
    protected function createServiceLocatorInterfaceMock()
    {
        /** @var ServiceLocatorInterface|MockObject $serviceLocator */
        $serviceLocator = $this->createMock(ServiceLocatorInterface::class);

        return $serviceLocator;
    }

    protected function getServiceNotFoundException(): string
    {
        if (method_exists($this->manager, 'configure')) {
            return InvalidServiceException::class;
        }
        return RuntimeException::class;
    }
}
