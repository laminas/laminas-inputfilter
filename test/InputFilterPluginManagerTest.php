<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorPluginManager;
use LaminasTest\InputFilter\FileInput\TestAsset\InitializableInputFilterInterface;
use LaminasTest\InputFilter\TestAsset\InputFilterInterfaceStub;
use LaminasTest\InputFilter\TestAsset\InputInterfaceStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use stdClass;
use Throwable;

use function method_exists;

#[CoversClass(InputFilterPluginManager::class)]
class InputFilterPluginManagerTest extends TestCase
{
    private InputFilterPluginManager $manager;
    private ServiceManager $services;

    protected function setUp(): void
    {
        $this->services = new ServiceManager();
        $this->manager  = new InputFilterPluginManager($this->services);
    }

    public function testIsASubclassOfAbstractPluginManager(): void
    {
        self::assertInstanceOf(AbstractPluginManager::class, $this->manager);
    }

    public function testIsNotSharedByDefault(): void
    {
        $r = new ReflectionObject($this->manager);
        $p = $r->getProperty('sharedByDefault');
        self::assertFalse($p->getValue($this->manager));
    }

    public function testRegisteringInvalidElementRaisesException(): void
    {
        $this->expectException($this->getServiceNotFoundException());
        $this->expectExceptionMessage(
            'must implement Laminas\InputFilter\InputFilterInterface or Laminas\InputFilter\InputInterface'
        );
        /** @psalm-suppress InvalidArgument */
        $this->manager->setService('test', $this);
    }

    public function testLoadingInvalidElementRaisesException(): void
    {
        $this->manager->setInvokableClass('test', stdClass::class);
        $this->expectException($this->getServiceNotFoundException());
        $this->manager->get('test');
    }

    /** @psalm-return array<string, array{0: string, 1: class-string<InputFilter>}> */
    public static function defaultInvokableClassesProvider(): array
    {
        return [
            // Description => [$alias, $expectedInstance]
            'inputfilter' => ['inputfilter', InputFilter::class],
            'collection'  => ['collection', CollectionInputFilter::class],
        ];
    }

    /**
     * @psalm-param class-string $expectedInstance
     */
    #[DataProvider('defaultInvokableClassesProvider')]
    public function testDefaultInvokableClasses(string $alias, string $expectedInstance): void
    {
        /** @var object $service */
        $service = $this->manager->get($alias);

        self::assertInstanceOf($expectedInstance, $service, 'get() return type not match');
    }

    public function testInputFilterInvokableClassSMDependenciesArePopulatedWithoutServiceLocator(): void
    {
        /** @var InputFilter $service */
        $service = $this->manager->get('inputfilter');

        $factory = $service->getFactory();
        self::assertSame(
            $this->manager,
            $factory->getInputFilterManager(),
            'Factory::getInputFilterManager() is not populated with the expected plugin manager'
        );
    }

    public function testInputFilterInvokableClassSMDependenciesArePopulatedWithServiceLocator(): void
    {
        $filterManager    = $this->createMock(FilterPluginManager::class);
        $validatorManager = $this->createMock(ValidatorPluginManager::class);

        $this->services->setService(FilterPluginManager::class, $filterManager);
        $this->services->setService(ValidatorPluginManager::class, $validatorManager);

        /** @var InputFilter $service */
        $service = $this->manager->get('inputfilter');

        $factory = $service->getFactory();
        self::assertSame(
            $this->manager,
            $factory->getInputFilterManager(),
            'Factory::getInputFilterManager() is not populated with the expected plugin manager'
        );

        $defaultFilterChain = $factory->getDefaultFilterChain();
        self::assertInstanceOf(FilterChain::class, $defaultFilterChain);
        self::assertSame(
            $filterManager,
            $defaultFilterChain->getPluginManager(),
            'Factory::getDefaultFilterChain() is not populated with the expected plugin manager'
        );

        $defaultValidatorChain = $factory->getDefaultValidatorChain();
        self::assertInstanceOf(ValidatorChain::class, $defaultValidatorChain);
        self::assertSame(
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
    public static function serviceProvider(): array
    {
        $inputFilterInterfaceMock = new InputFilterInterfaceStub();
        $inputInterfaceMock       = new InputInterfaceStub('foo', true);

        // phpcs:disable Generic.Files.LineLength.TooLong
        return [
            // Description         => [$serviceName,                  $service,                  $instanceOf]
            'InputFilterInterface' => ['inputFilterInterfaceService', $inputFilterInterfaceMock, InputFilterInterface::class],
            'InputInterface'       => ['inputInterfaceService',       $inputInterfaceMock,       InputInterface::class],
        ];
        // phpcs:enable
    }

    #[DataProvider('serviceProvider')]
    public function testGet(string $serviceName, InputInterface|InputFilterInterface $service): void
    {
        $this->manager->setService($serviceName, $service);

        self::assertSame($service, $this->manager->get($serviceName), 'get() value not match');
    }

    public function testServicesAreInitiatedIfImplementsInitializableInterface(): void
    {
        $mock = $this->createMock(InitializableInputFilterInterface::class);
        // Init is called twice. Once during `setService` and once during `get`
        $mock->expects(self::exactly(2))->method('init');
        $this->manager->setService('PluginName', $mock);
        self::assertSame($mock, $this->manager->get('PluginName'), 'get() value not match');
    }

    public function testPopulateFactoryCanAcceptInputFilterAsFirstArgumentAndWillUseFactoryWhenItDoes(): void
    {
        $inputFilter = new InputFilter();
        $this->manager->populateFactory($inputFilter);

        self::assertSame($this->manager, $inputFilter->getFactory()->getInputFilterManager());
    }

    /** @return class-string<Throwable> */
    protected function getServiceNotFoundException(): string
    {
        if (method_exists($this->manager, 'configure')) {
            return InvalidServiceException::class;
        }
        return RuntimeException::class;
    }
}
