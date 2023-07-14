<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Traversable;

use function is_array;

/**
 * @psalm-import-type InputSpecification from InputFilterInterface
 * @template TFilteredValues
 * @extends BaseInputFilter<TFilteredValues>
 */
class InputFilter extends BaseInputFilter
{
    /** @var Factory|null */
    protected $factory;

    /**
     * Set factory to use when adding inputs and filters by spec
     *
     * @return InputFilter
     */
    public function setFactory(Factory $factory)
    {
        $this->factory = $factory;
        return $this;
    }

    /**
     * Get factory to use when adding inputs and filters by spec
     *
     * Lazy-loads a Factory instance if none attached.
     *
     * @return Factory
     */
    public function getFactory()
    {
        if (null === $this->factory) {
            $this->factory = new Factory();
        }
        return $this->factory;
    }

    /**
     * Add an input to the input filter
     *
     * @param  InputSpecification|Traversable|InputInterface|InputFilterInterface $input
     * @param  array-key|null $name
     * @return $this
     */
    public function add($input, $name = null)
    {
        if (
            is_array($input)
            || ($input instanceof Traversable && ! $input instanceof InputFilterInterface)
        ) {
            $factory = $this->getFactory();
            $input   = $factory->createInput($input);
        }

        // At this point $input is potentially invalid. parent::add() will throw an exception in this case.

        parent::add($input, $name);

        return $this;
    }
}
