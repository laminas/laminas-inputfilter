<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\AbstractSingleInstancePluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\InitializableInterface;

use function get_debug_type;
use function sprintf;

/**
 * Plugin manager implementation for input filters.
 *
 * @link ServiceManager
 *
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @psalm-type InstanceType = InputFilterInterface|InputInterface
 * @extends AbstractSingleInstancePluginManager<InstanceType>
 */
final class InputFilterPluginManager extends AbstractSingleInstancePluginManager
{
    /**
     * Default alias of plugins
     *
     * @var string[]
     */
    protected array $aliases = [
        'inputfilter'         => InputFilter::class,
        'inputFilter'         => InputFilter::class,
        'InputFilter'         => InputFilter::class,
        'collection'          => CollectionInputFilter::class,
        'Collection'          => CollectionInputFilter::class,
        'optionalinputfilter' => OptionalInputFilter::class,
        'optionalInputFilter' => OptionalInputFilter::class,
        'OptionalInputFilter' => OptionalInputFilter::class,

        // v2 normalized FQCNs
        'zendinputfilterinputfilter'           => InputFilter::class,
        'zendinputfiltercollectioninputfilter' => CollectionInputFilter::class,
        'zendinputfilteroptionalinputfilter'   => OptionalInputFilter::class,
    ];

    /**
     * Default set of plugins
     *
     * @var string[]
     */
    protected array $factories = [
        InputFilter::class           => InputFilterFactory::class,
        CollectionInputFilter::class => InputFilterFactory::class,
        OptionalInputFilter::class   => InputFilterFactory::class,
    ];

    protected bool $sharedByDefault = false;

    /**
     * @inheritDoc
     * @psalm-assert InstanceType $instance
     * @param mixed $instance
     */
    public function validate(mixed $instance): void
    {
        if ($instance instanceof InputFilterInterface || $instance instanceof InputInterface) {
            // Hook to perform various initialization, when the inputFilter is not created through the factory
            if ($instance instanceof InitializableInterface) {
                $instance->init();
            }

            // we're okay
            return;
        }

        throw new InvalidServiceException(sprintf(
            'Plugin of type %s is invalid; must implement %s or %s',
            get_debug_type($instance),
            InputFilterInterface::class,
            InputInterface::class
        ));
    }
}
