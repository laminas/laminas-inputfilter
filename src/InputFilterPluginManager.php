<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Closure;
use Laminas\ServiceManager\AbstractSingleInstancePluginManager;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\InitializableInterface;
use Psr\Container\ContainerInterface;

use function array_replace_recursive;

/**
 * Plugin manager implementation for input filters.
 *
 * @link ServiceManager
 *
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @extends AbstractSingleInstancePluginManager<InputFilterInterface>
 *
 * @final
 */
class InputFilterPluginManager extends AbstractSingleInstancePluginManager
{
    private const DEFAULT_CONFIGURATION = [
        'factories' => [
            InputFilter::class           => InvokableFactory::class,
            CollectionInputFilter::class => InvokableFactory::class,
            OptionalInputFilter::class   => InvokableFactory::class,
            // v2 canonical FQCN
            'laminasinputfilterinputfilter'           => InvokableFactory::class,
            'laminasinputfiltercollectioninputfilter' => InvokableFactory::class,
            'laminasinputfilteroptionalinputfilter'   => InvokableFactory::class,
        ],
        'aliases'   => [
            'inputfilter'         => InputFilter::class,
            'inputFilter'         => InputFilter::class,
            'InputFilter'         => InputFilter::class,
            'collection'          => CollectionInputFilter::class,
            'Collection'          => CollectionInputFilter::class,
            'optionalinputfilter' => OptionalInputFilter::class,
            'optionalInputFilter' => OptionalInputFilter::class,
            'OptionalInputFilter' => OptionalInputFilter::class,

            // Legacy Zend Framework aliases
            'Zend\InputFilter\InputFilter'           => InputFilter::class,
            'Zend\InputFilter\CollectionInputFilter' => CollectionInputFilter::class,
            'Zend\InputFilter\OptionalInputFilter'   => OptionalInputFilter::class,

            // v2 normalized FQCNs
            'zendinputfilterinputfilter'           => InputFilter::class,
            'zendinputfiltercollectioninputfilter' => CollectionInputFilter::class,
            'zendinputfilteroptionalinputfilter'   => OptionalInputFilter::class,
        ],
    ];

    /**
     * Whether to share by default; default to false
     */
    protected bool $sharedByDefault = false;

    protected string $instanceOf = InputFilterInputInterface::class;

    /**
     * @param ServiceManagerConfiguration $config
     */
    public function __construct(?ContainerInterface $creationContext = null, array $config = [])
    {
        /** @var ServiceManagerConfiguration $config */
        $config = array_replace_recursive(self::DEFAULT_CONFIGURATION, $config);
        parent::__construct($creationContext, $config);

        $this->configure([
            'initializers' => [
                Closure::fromCallable([$this, 'injectFactory']),
                Closure::fromCallable([$this, 'injectInitializable']),
            ],
        ]);
    }

    /**
     * @internal
     */
    protected function injectFactory(ContainerInterface $container, object $inputFilter): void
    {
        if (! $inputFilter instanceof InputFilter) {
            return;
        }

        $factory = $container->get(Factory::class);
        $factory->setInputFilterManager($this);
        $inputFilter->setFactory($factory);
    }

    /**
     * @internal
     */
    protected function injectInitializable(ContainerInterface $container, object $inputFilter): void
    {
        if (! $inputFilter instanceof InitializableInterface) {
            return;
        }

        // Hook to perform various initialization, when the inputFilter is not created through the factory
        $inputFilter->init();
    }
}
