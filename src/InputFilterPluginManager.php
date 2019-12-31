<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\InputFilter;

use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\ConfigInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\InitializableInterface;

/**
 * Plugin manager implementation for input filters.
 *
 * @method InputFilterInterface|InputInterface get($name)
 */
class InputFilterPluginManager extends AbstractPluginManager
{
    /**
     * Default set of plugins
     *
     * @var string[]
     */
    protected $invokableClasses = [
        'inputfilter' => InputFilter::class,
        'collection'  => CollectionInputFilter::class,
    ];

    /**
     * Whether or not to share by default
     *
     * @var bool
     */
    protected $shareByDefault = false;

    /**
     * @param ConfigInterface $configuration
     */
    public function __construct(ConfigInterface $configuration = null)
    {
        parent::__construct($configuration);

        $this->addInitializer([$this, 'populateFactory']);
    }

    /**
     * Inject this and populate the factory with filter chain and validator chain
     *
     * @param $inputFilter
     */
    public function populateFactory($inputFilter)
    {
        if ($inputFilter instanceof InputFilter) {
            $factory = $inputFilter->getFactory();

            $factory->setInputFilterManager($this);

            if ($this->serviceLocator instanceof ServiceLocatorInterface) {
                $factory->getDefaultFilterChain()->setPluginManager($this->serviceLocator->get('FilterManager'));
                $factory->getDefaultValidatorChain()->setPluginManager($this->serviceLocator->get('ValidatorManager'));
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function validatePlugin($plugin)
    {
        if ($plugin instanceof InputFilterInterface || $plugin instanceof InputInterface) {
            // Hook to perform various initialization, when the inputFilter is not created through the factory
            if ($plugin instanceof InitializableInterface) {
                $plugin->init();
            }

            // we're okay
            return;
        }

        throw new Exception\RuntimeException(sprintf(
            'Plugin of type %s is invalid; must implement %s or %s',
            (is_object($plugin) ? get_class($plugin) : gettype($plugin)),
            InputFilterInterface::class,
            InputInterface::class
        ));
    }
}
