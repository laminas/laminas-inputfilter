<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\ServiceManager\AbstractSingleInstancePluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\InitializableInterface;
use Psr\Container\ContainerInterface;

use function array_replace_recursive;
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
    private const DEFAULT_CONFIGURATION = [
        'factories' => [
            InputFilter::class           => InputFilterFactory::class,
            CollectionInputFilter::class => InputFilterFactory::class,
            OptionalInputFilter::class   => InputFilterFactory::class,
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
        ],
    ];

    /** @var class-string<InputFilterInterface> */
    protected string $instanceOf = InputFilterInterface::class;

    protected bool $sharedByDefault = false;

    public function __construct(
        ContainerInterface $creationContext,
        array $config = [],
    ) {
        /** @psalm-var ServiceManagerConfiguration $config */
        $config = array_replace_recursive(self::DEFAULT_CONFIGURATION, $config);

        parent::__construct($creationContext, $config);
    }

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
            InputInterface::class,
        ));
    }
}
