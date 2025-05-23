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
    protected ?Factory $factory;

    /**
     * Set factory to use when adding inputs and filters by spec
     *
     * @return $this
     */
    public function setFactory(Factory $factory): static
    {
        $this->factory = $factory;
        return $this;
    }

    /**
     * Get factory to use when adding inputs and filters by spec
     *
     * Lazy-loads a Factory instance if none attached.
     */
    public function getFactory(): ?Factory
    {
        return $this->factory;
    }

    /**
     * Add an input to the input filter
     *
     * @param InputFilterInputInterface|iterable|InputSpecification $input
     * @param array-key|null $name
     * @return $this
     */
    public function add(InputFilterInputInterface|iterable $input, int|string|null $name = null): static
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
