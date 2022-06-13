<?php

namespace Laminas\InputFilter;

use Interop\Container\ContainerInterface; // phpcs:ignore
use Laminas\Filter\FilterPluginManager;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\ConfigInterface;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\InitializableInterface;
use Laminas\Validator\ValidatorPluginManager;
use Psr\Container\ContainerInterface as PsrContainerInterface;

use function get_class;
use function gettype;
use function is_object;
use function sprintf;

/**
 * Plugin manager implementation for input filters.
 *
 * @link ServiceManager
 *
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @template InstanceType of InputFilterInterface|InputInterface
 * @extends AbstractPluginManager<InstanceType>
 * @method InputFilterInterface|InputInterface get(string $name, ?array $options = null)
 */
class InputFilterPluginManager extends AbstractPluginManager
{
    /**
     * Default alias of plugins
     *
     * @var string[]
     */
    protected $aliases = [
        'inputfilter'         => InputFilter::class,
        'inputFilter'         => InputFilter::class,
        'InputFilter'         => InputFilter::class,
        'collection'          => CollectionInputFilter::class,
        'Collection'          => CollectionInputFilter::class,
        'optionalinputfilter' => OptionalInputFilter::class,
        'optionalInputFilter' => OptionalInputFilter::class,
        'OptionalInputFilter' => OptionalInputFilter::class,

        // Legacy Zend Framework aliases
        \Zend\InputFilter\InputFilter::class           => InputFilter::class,
        \Zend\InputFilter\CollectionInputFilter::class => CollectionInputFilter::class,
        \Zend\InputFilter\OptionalInputFilter::class   => OptionalInputFilter::class,

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
    protected $factories = [
        InputFilter::class           => InvokableFactory::class,
        CollectionInputFilter::class => InvokableFactory::class,
        OptionalInputFilter::class   => InvokableFactory::class,
        // v2 canonical FQCN
        'laminasinputfilterinputfilter'           => InvokableFactory::class,
        'laminasinputfiltercollectioninputfilter' => InvokableFactory::class,
        'laminasinputfilteroptionalinputfilter'   => InvokableFactory::class,
    ];

    /**
     * Whether or not to share by default (v3)
     *
     * @var bool
     */
    protected $sharedByDefault = false;

    /**
     * Whether or not to share by default (v2)
     *
     * @deprecated Since 2.15.0 This property will be removed in version 3.0 because
     *             it is only relevant to ServiceManager version 2.x
     *
     * @var bool
     */
    protected $shareByDefault = false;

    /**
     * @param null|ConfigInterface|ContainerInterface|PsrContainerInterface $configOrContainer
     * @param ServiceManagerConfiguration $v3config
     */
    public function __construct($configOrContainer = null, array $v3config = [])
    {
        $this->initializers[] = [$this, 'populateFactory'];
        parent::__construct($configOrContainer, $v3config);
    }

    /**
     * Inject this and populate the factory with filter chain and validator chain
     *
     * @param self|InputFilter $containerOrInputFilter
     * @param InputFilter|null $inputFilter
     * @return void
     */
    public function populateFactory($containerOrInputFilter, $inputFilter = null)
    {
        $inputFilter = $inputFilter ?: $containerOrInputFilter;

        if (! $inputFilter instanceof InputFilter) {
            return;
        }

        $factory = $inputFilter->getFactory();
        $factory->setInputFilterManager($this);
    }

    /**
     * Populate the filter and validator managers for the default filter/validator chains.
     *
     * @return void
     */
    public function populateFactoryPluginManagers(Factory $factory)
    {
        /** @psalm-suppress DocblockTypeContradiction */
        if (! $this->creationContext) {
            return;
        }

        $filterChain = $factory->getDefaultFilterChain();
        if ($filterChain !== null && $this->creationContext->has(FilterPluginManager::class)) {
            $filterChain->setPluginManager($this->creationContext->get(FilterPluginManager::class));
        }

        $validatorChain = $factory->getDefaultValidatorChain();
        if ($validatorChain !== null && $this->creationContext->has(ValidatorPluginManager::class)) {
            $validatorChain->setPluginManager($this->creationContext->get(ValidatorPluginManager::class));
        }
    }

    /**
     * {@inheritDoc} (v3)
     *
     * @psalm-assert InstanceType $instance
     */
    public function validate($instance)
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
            is_object($instance) ? get_class($instance) : gettype($instance),
            InputFilterInterface::class,
            InputInterface::class
        ));
    }

    /**
     * Validate the plugin (v2)
     *
     * Checks that the filter loaded is either a valid callback or an instance
     * of FilterInterface.
     *
     * @deprecated Since 2.14.0. This method is only relevant to version 2 of laminas-servicemanager which is no
     *             longer installable in this library.
     *
     * @see validate()
     *
     * @param  mixed                      $plugin
     * @return void
     * @throws Exception\RuntimeException If invalid.
     */
    public function validatePlugin($plugin)
    {
        try {
            $this->validate($plugin);
        } catch (InvalidServiceException $e) {
            throw new Exception\RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
