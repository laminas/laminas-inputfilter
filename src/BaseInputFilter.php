<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\InitializableInterface;
use ReturnTypeWillChange;
use Traversable;

use function array_diff;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function assert;
use function count;
use function func_get_args;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * @psalm-import-type InputSpecification from InputFilterInterface
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 * @template TFilteredValues
 * @implements InputFilterInterface<TFilteredValues>
 */
class BaseInputFilter implements
    InputFilterInterface,
    UnknownInputsCapableInterface,
    InitializableInterface,
    ReplaceableInputInterface,
    UnfilteredDataInterface
{
    public function __construct(
        protected readonly Factory $factory,
    ) {
    }

    /** @var array<array-key, mixed>|null */
    protected $data;

    /** @var array<array-key, mixed> */
    protected $unfilteredData = [];

    /** @var array<array-key, InputInterface|InputFilterInterface> */
    protected $inputs = [];

    /** @var array<array-key, InputInterface|InputFilterInterface>|null */
    protected $invalidInputs;

    /** @var null|array<array-key, string> Input names */
    protected $validationGroup;

    /** @var array<array-key, InputInterface|InputFilterInterface>|null */
    protected $validInputs;

    /**
     * This function is automatically called when creating element with factory. It
     * allows to perform various operations (add elements...)
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Countable: number of inputs in this input filter
     *
     * Only details the number of direct children.
     *
     * @return int
     */
    #[ReturnTypeWillChange]
    public function count()
    {
        return count($this->inputs);
    }

    /** @inheritDoc */
    public function add($input, $name = null): static
    {
        if (is_array($input)) {
            $input = $this->factory->create($input);
        }

        if (! $input instanceof InputInterface && ! $input instanceof InputFilterInterface) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an instance of %s or %s as its first argument; received "%s"',
                __METHOD__,
                InputInterface::class,
                InputFilterInterface::class,
                get_debug_type($input),
            ));
        }

        if ($input instanceof InputInterface && ($name === null || $name === '' || is_int($name))) {
            $name = $input->getName();
            if ($name === null || $name === '') {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s: input instance must have a valid name or input name must be provided as a parameter',
                    __METHOD__,
                ));
            }
        }

        if (! is_string($name) && ! is_int($name)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: input name expected to be string or int, %s given',
                __METHOD__,
                get_debug_type($name)
            ));
        }

        if ($name === '') {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: input name can not be an empty string',
                __METHOD__,
            ));
        }

        if (
            isset($this->inputs[$name])
            && $this->inputs[$name] instanceof InputInterface
            && $input instanceof InputInterface
        ) {
            // The element already exists, so merge the config. Please note
            // that this merges the new input into the original.
            $original = $this->inputs[$name];
            $original->merge($input);
            return $this;
        }

        $this->inputs[$name] = $input;
        return $this;
    }

    /**
     * Replace a named input
     *
     * @param  InputInterface|InputFilterInterface|InputSpecification|InputFilterSpecification $input
     * @param  array-key                           $name Name of the input to replace
     * @throws Exception\InvalidArgumentException If input to replace not exists.
     */
    public function replace($input, $name): static
    {
        /** @psalm-suppress DocblockTypeContradiction  */
        if (! is_string($name) && ! is_int($name)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: input name expected to be string or int, %s given',
                __METHOD__,
                get_debug_type($name),
            ));
        }

        if (! array_key_exists($name, $this->inputs)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: no input found matching "%s"',
                __METHOD__,
                $name
            ));
        }

        $this->remove($name);
        $this->add($input, $name);

        return $this;
    }

    /**
     * Retrieve a named input
     *
     * @throws Exception\InvalidArgumentException
     */
    public function get(int|string $name): InputInterface|InputFilterInterface
    {
        if (! array_key_exists($name, $this->inputs)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: no input found matching "%s"',
                __METHOD__,
                $name
            ));
        }
        return $this->inputs[$name];
    }

    public function has(int|string $name): bool
    {
        return array_key_exists($name, $this->inputs);
    }

    public function remove(int|string $name): static
    {
        unset($this->inputs[$name]);
        return $this;
    }

    /**
     * Set data to use when validating and filtering
     *
     * @param  iterable|null $data null is cast to an empty array.
     * @throws Exception\InvalidArgumentException
     */
    public function setData($data): static
    {
        // A null value indicates an empty set
        if (null === $data) {
            $data = [];
        }

        if ($data instanceof Traversable) {
            $data = ArrayUtils::iteratorToArray($data);
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (! is_array($data)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable argument; received %s',
                __METHOD__,
                get_debug_type($data),
            ));
        }

        $this->setUnfilteredData($data);

        $this->data = $data;
        $this->populate();

        return $this;
    }

    /**
     * Is the data set valid?
     *
     * @param  mixed|null $context
     * @throws Exception\RuntimeException
     */
    public function isValid($context = null): bool
    {
        if (null === $this->data) {
            throw new Exception\RuntimeException(sprintf(
                '%s: no data present to validate!',
                __METHOD__
            ));
        }

        $inputs = $this->validationGroup ?? array_keys($this->inputs);
        return $this->validateInputs($inputs, $this->data, $context);
    }

    /**
     * Validate a set of inputs against the current data
     *
     * @param  array<array-key, array-key> $inputs Array of input names.
     * @param  array<array-key, mixed> $data
     * @param  mixed|null $context
     * @return bool
     */
    protected function validateInputs(array $inputs, array $data = [], $context = null)
    {
        $inputContext = $context ?? array_merge($this->getRawValues(), $data);

        $this->validInputs   = [];
        $this->invalidInputs = [];
        $valid               = true;

        foreach ($inputs as $name) {
            $input = $this->inputs[$name];

            // Validate an input filter
            if ($input instanceof InputFilterInterface) {
                if (! $input->isValid($context)) {
                    $this->invalidInputs[$name] = $input;
                    $valid                      = false;
                    continue;
                }
                $this->validInputs[$name] = $input;
                continue;
            }

            // If input is not InputInterface then silently continue (BC safe)
            if (! $input instanceof InputInterface) {
                continue;
            }

            // If input is optional (not required), and value is not set, then ignore.
            if (
                ! array_key_exists($name, $data)
                && ! $input->isRequired()
            ) {
                continue;
            }

            // Validate an input
            if (! $input->isValid($inputContext)) {
                // Validation failure
                $this->invalidInputs[$name] = $input;
                $valid                      = false;

                if ($input->breakOnFailure()) {
                    return false;
                }
                continue;
            }
            $this->validInputs[$name] = $input;
        }

        return $valid;
    }

    /**
     * Provide a list of one or more elements indicating the complete set to validate
     *
     * When provided, calls to {@link isValid()} will only validate the provided set.
     *
     * If the initial value is {@link VALIDATE_ALL}, the current validation group, if
     * any, should be cleared.
     *
     * Implementations should allow passing a single array value, or multiple arguments,
     * each specifying a single input.
     *
     * @param  array-key|array<array-key, array-key> $name
     * @throws Exception\InvalidArgumentException
     */
    public function setValidationGroup($name): static
    {
        if ($name === self::VALIDATE_ALL) {
            $this->validationGroup = null;
            foreach ($this->getInputs() as $input) {
                if ($input instanceof InputFilterInterface) {
                    $input->setValidationGroup(self::VALIDATE_ALL);
                }
            }
            return $this;
        }

        if (is_array($name)) {
            $inputs = [];
            foreach ($name as $key => $value) {
                if (! $this->has($key)) {
                    $inputs[] = $value;
                    continue;
                }

                $inputs[] = $key;

                $input = $this->inputs[$key];
                if ($input instanceof InputFilterInterface) {
                    // Recursively populate validation groups for sub input filters
                    $input->setValidationGroup($value);
                }
            }
        } else {
            $inputs = func_get_args();
        }

        if (! empty($inputs)) {
            $this->validateValidationGroup($inputs);
            $this->validationGroup = $inputs;
        }

        return $this;
    }

    /**
     * Return a list of inputs that were invalid.
     *
     * Implementations should return an associative array of name/input pairs
     * that failed validation.
     *
     * @return array<array-key, InputInterface|InputFilterInterface>
     */
    public function getInvalidInput(): array
    {
        return is_array($this->invalidInputs) ? $this->invalidInputs : [];
    }

    /**
     * Return a list of inputs that were valid.
     *
     * Implementations should return an associative array of name/input pairs
     * that passed validation.
     *
     * @return array<array-key, InputInterface|InputFilterInterface>
     */
    public function getValidInput(): array
    {
        return is_array($this->validInputs) ? $this->validInputs : [];
    }

    /**
     * Retrieve a value from a named input
     *
     * @throws Exception\InvalidArgumentException
     */
    public function getValue(int|string $name): mixed
    {
        if (! array_key_exists($name, $this->inputs)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects a valid input name; "%s" was not found in the filter',
                __METHOD__,
                $name
            ));
        }
        $input = $this->inputs[$name];

        if ($input instanceof InputFilterInterface) {
            return $input->getValues();
        }

        return $input->getValue();
    }

    /**
     * Return a list of filtered values
     *
     * List should be an associative array, with the values filtered. If
     * validation failed, this should raise an exception.
     *
     * @return TFilteredValues
     */
    public function getValues(): array
    {
        $inputs = $this->validationGroup ?? array_keys($this->inputs);
        $values = [];
        foreach ($inputs as $name) {
            $input = $this->inputs[$name];

            $value = $input instanceof InputFilterInterface
                ? $input->getValues()
                : $input->getValue();

            /** @psalm-suppress MixedAssignment */
            $values[$name] = $value;
        }
        return $values;
    }

    /**
     * Retrieve a raw (unfiltered) value from a named input
     *
     * @throws Exception\InvalidArgumentException
     */
    public function getRawValue(int|string $name): mixed
    {
        if (! array_key_exists($name, $this->inputs)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects a valid input name; "%s" was not found in the filter',
                __METHOD__,
                $name
            ));
        }
        $input = $this->inputs[$name];
        if ($input instanceof InputFilterInterface) {
            return $input->getRawValues();
        }
        return $input->getRawValue();
    }

    /**
     * Return a list of unfiltered values
     *
     * List should be an associative array of named input/value pairs,
     * with the values unfiltered.
     *
     * @return array<array-key, mixed>
     */
    public function getRawValues(): array
    {
        $values = [];
        foreach ($this->inputs as $name => $input) {
            if ($input instanceof InputFilterInterface) {
                $values[$name] = $input->getRawValues();
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $values[$name] = $input->getRawValue();
        }
        return $values;
    }

    /**
     * Return a list of validation failure messages
     *
     * Should return an associative array of named input/message list pairs.
     * Pairs should only be returned for inputs that failed validation.
     *
     * @return array<array-key, array<array-key, string|array>>
     */
    public function getMessages(): array
    {
        $messages = [];
        foreach ($this->getInvalidInput() as $name => $input) {
            $messages[$name] = $input->getMessages();
        }

        return $messages;
    }

    /**
     * Ensure all names of a validation group exist as input in the filter
     *
     * @param  array<array-key, array-key> $inputs Input names
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    protected function validateValidationGroup(array $inputs)
    {
        foreach ($inputs as $name) {
            if (! array_key_exists($name, $this->inputs)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'setValidationGroup() expects a list of valid input names; "%s" was not found',
                    (string) $name
                ));
            }
        }
    }

    /**
     * Populate the values of all attached inputs
     *
     * @return void
     */
    protected function populate()
    {
        assert($this->data !== null);
        foreach (array_keys($this->inputs) as $name) {
            $input = $this->inputs[$name];

            if ($input instanceof CollectionInputFilter) {
                $input->clearValues();
                $input->clearRawValues();
            }

            if (! array_key_exists($name, $this->data)) {
                // No value; clear value in this input
                if ($input instanceof InputFilterInterface) {
                    $input->setData([]);
                    continue;
                }

                if ($input instanceof Input) {
                    $input->resetValue();
                    continue;
                }

                $input->setValue(null);
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $value = $this->data[$name];

            if ($input instanceof InputFilterInterface) {
                // Fixes #159
                if (! is_array($value) && ! $value instanceof Traversable) {
                    $value = [];
                }

                $input->setData($value);
                continue;
            }

            $input->setValue($value);
        }
    }

    /**
     * Is the data set has unknown input ?
     *
     * @throws Exception\RuntimeException
     */
    public function hasUnknown(): bool
    {
        return $this->getUnknown() ? true : false;
    }

    /**
     * Return the unknown input
     *
     * @throws Exception\RuntimeException
     * @return array
     */
    public function getUnknown()
    {
        if (null === $this->data) {
            throw new Exception\RuntimeException(sprintf(
                '%s: no data present!',
                __METHOD__
            ));
        }

        $data   = array_keys($this->data);
        $inputs = array_keys($this->inputs);
        $diff   = array_diff($data, $inputs);

        $unknownInputs = [];
        $intersect     = array_intersect($diff, $data);
        if (! empty($intersect)) {
            foreach ($intersect as $key) {
                $unknownInputs[$key] = $this->data[$key];
            }
        }

        return $unknownInputs;
    }

    /**
     * Get an array of all inputs
     *
     * @return array<array-key, InputInterface|InputFilterInterface>
     */
    public function getInputs()
    {
        return $this->inputs;
    }

    /**
     * Merges the inputs from an InputFilter into the current one
     *
     * @return $this
     */
    public function merge(BaseInputFilter $inputFilter): static
    {
        foreach ($inputFilter->getInputs() as $name => $input) {
            $this->add($input, $name);
        }

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getUnfilteredData()
    {
        return $this->unfilteredData;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return $this
     */
    public function setUnfilteredData($data): static
    {
        $this->unfilteredData = $data;
        return $this;
    }
}
