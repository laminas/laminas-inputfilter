<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\InputFilter\Exception\InputNotFoundException;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\InitializableInterface;
use NoDiscard;
use Traversable;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function assert;
use function count;
use function func_get_args;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_iterable;
use function is_string;
use function iterator_to_array;
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
    protected array|null $data = null;

    /** @var array<array-key, mixed> */
    protected array $unfilteredData = [];

    /** @var array<array-key, InputInterface|InputFilterInterface> */
    protected array $inputs = [];

    /** @var array<array-key, InputInterface|InputFilterInterface>|null */
    protected array|null $invalidInputs = null;

    /** @var array<array-key, InputInterface|InputFilterInterface>|null */
    protected array|null $validInputs = null;

    /** @var null|list<array-key> Input names */
    protected array|null $validationGroup = null;

    /**
     * This function is automatically called when creating element with factory. It
     * allows to perform various operations (add elements...)
     */
    public function init(): void
    {
    }

    /**
     * Countable: number of inputs in this input filter
     *
     * Only details the number of direct children.
     */
    public function count(): int
    {
        return count($this->inputs);
    }

    private function fromArray(
        array $spec,
        string|int|null $name,
    ): InputInterface|InputFilterInterface {
        if (! $this->factory->isInputSpecification($spec)) {
            return $this->factory->createInputFilter($spec);
        }

        /**
         * The name in the specification array takes precedence over the argument
         */
        $name = isset($spec['name']) && is_string($spec['name']) && $spec['name'] !== ''
            ? $spec['name']
            : $name;

        if ($name === null || $name === '') {
            throw new InvalidArgumentException('All inputs must have a non-empty-string or integer name');
        }

        $spec['name'] = $name;

        return $this->factory->createInput($spec);
    }

    /** @inheritDoc */
    public function add(
        InputInterface|InputFilterInterface|array $input,
        string|int|null $name = null,
    ): static {
        if (is_array($input)) {
            $input = $this->fromArray($input, $name);
        }

        if ($input instanceof InputInterface && ($name === null || $name === '' || is_int($name))) {
            $name = $input->getName();
        }

        if (! $this->isKey($name)) {
            throw new InvalidArgumentException(sprintf(
                'Input or InputFilter name must be a non-empty string or an int, %s given',
                get_debug_type($name),
            ));
        }

        if (
            isset($this->inputs[$name])
            && $this->inputs[$name] instanceof MutableInputInterface
            && $input instanceof InputInterface
        ) {
            // The element already exists, so merge the config. Please note
            // that this merges the new input into the original.
            $this->inputs[$name]->merge($input);
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
     * @throws InputNotFoundException If input to replace not exists.
     */
    public function replace(
        InputInterface|InputFilterInterface|array $input,
        int|string $name,
    ): static {
        if (! array_key_exists($name, $this->inputs)) {
            throw InputNotFoundException::forKey($name);
        }

        $this->remove($name);
        $this->add($input, $name);

        return $this;
    }

    /**
     * Retrieve a named input
     *
     * @throws InputNotFoundException
     */
    public function get(int|string $name): InputInterface|InputFilterInterface
    {
        if (! array_key_exists($name, $this->inputs)) {
            throw InputNotFoundException::forKey($name);
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

    /** @inheritDoc */
    public function setData(iterable|null $data): static
    {
        // A null value indicates an empty set
        if (null === $data) {
            $data = [];
        }

        if ($data instanceof Traversable) {
            $data = ArrayUtils::iteratorToArray($data);
        }

        $this->setUnfilteredData($data);

        $this->data = $data;
        $this->populate();

        return $this;
    }

    /** @inheritDoc */
    public function isValid(array|null $context = null): bool
    {
        if (null === $this->data) {
            throw new Exception\RuntimeException(sprintf(
                '%s: no data present to validate!',
                __METHOD__,
            ));
        }

        $inputs = $this->validationGroup ?? array_keys($this->inputs);
        return $this->validateInputs($inputs, $this->data, $context);
    }

    #[NoDiscard]
    public function validate(iterable $data, array $context = []): InputFilterValidationResult
    {
        $data    = iterator_to_array($data);
        $context = $context === [] ? $data : $context;
        $results = [];
        foreach ($this->inputs as $name => $input) {
            /** @psalm-var mixed $value */
            $value = $data[$name] ?? null;

            if ($input instanceof InputFilterInterface) {
                $value = is_iterable($value) ? iterator_to_array($value) : [];

                $result = $input->validate($value, $context);
                assert($result instanceof InputFilterValidationResult);
                $results[$name] = $result;

                continue;
            }

            $result         = $input->validate($value, $context);
            $results[$name] = $result;
            if (! $result->valid() && $input->breakOnFailure()) {
                break;
            }
        }

        return new InputFilterValidationResult($results);
    }

    /**
     * Validate a set of inputs against the current data
     *
     * @param  list<array-key> $inputs A list of input names to validate
     * @param  array<array-key, mixed> $data
     * @param  array<array-key, mixed>|null $context
     */
    protected function validateInputs(array $inputs, array $data, array|null $context = null): bool
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

            assert($input instanceof InputInterface);

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
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    public function setValidationGroup(int|string|array $name): static
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

        $inputs = [];

        if (! is_array($name)) {
            $name = func_get_args();
        }

        /** @psalm-var mixed $value */
        foreach ($name as $key => $value) {
            if ($this->isKey($value) && $this->has($value)) {
                $inputs[] = $value;
                continue;
            }

            if (is_array($value) && $this->has($key)) {
                $input = $this->get($key);
                if ($input instanceof InputFilterInterface) {
                    // Recursively populate validation groups for sub input filters
                    $input->setValidationGroup($value);
                }

                $inputs[] = $key;
                continue;
            }

            if ($this->has($key)) {
                $inputs[] = $key;
                continue;
            }

            $missing = is_string($value) ? $value : $key;

            throw InputNotFoundException::forKey($missing);
        }

        if ($inputs !== []) {
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
     * @throws InvalidArgumentException
     */
    public function getValue(int|string $name): mixed
    {
        if (! array_key_exists($name, $this->inputs)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a valid input name; "%s" was not found in the filter',
                __METHOD__,
                $name,
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

    public function getRawValue(int|string $name): mixed
    {
        $input = $this->get($name);

        return $input instanceof InputFilterInterface
            ? $input->getRawValues()
            : $input->getRawValue();
    }

    /** @inheritDoc */
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

    public function getMessages(): ErrorMessages
    {
        return new ErrorMessages(array_map(
            static fn (InputInterface|InputFilterInterface $input): ErrorMessages => $input->getMessages(),
            $this->getInvalidInput(),
        ));
    }

    /**
     * Ensure all names of a validation group exist as input in the filter
     *
     * @param array<array-key, mixed> $inputs Input names
     * @throws InputNotFoundException
     * @psalm-assert list<array-key> $inputs
     */
    protected function validateValidationGroup(array $inputs): void
    {
        foreach ($inputs as $name) {
            if (! $this->isKey($name) || ! array_key_exists($name, $this->inputs)) {
                throw InputNotFoundException::forKey((string) $name);
            }
        }
    }

    /**
     * Populate the values of all attached inputs
     */
    protected function populate(): void
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

            /** @psalm-var mixed $value */
            $value = $this->data[$name];

            if ($input instanceof InputFilterInterface) {
                // Fixes #159
                if (! is_iterable($value)) {
                    $value = [];
                }

                $input->setData($value);
                continue;
            }

            $input->setValue($value);
        }
    }

    /**
     * @inheritDoc
     * @throws Exception\RuntimeException
     */
    public function hasUnknown(): bool
    {
        return (bool) $this->getUnknown();
    }

    /**
     * @inheritDoc
     * @throws Exception\RuntimeException
     */
    public function getUnknown(): array
    {
        if (null === $this->data) {
            throw new Exception\RuntimeException(sprintf(
                '%s: no data present!',
                __METHOD__,
            ));
        }

        $unknown = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($this->data as $key => $value) {
            if ($this->has($key)) {
                continue;
            }

            $unknown[$key] = $value;
        }

        return $unknown;
    }

    /**
     * Get an array of all inputs
     *
     * @return array<array-key, InputInterface|InputFilterInterface>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /**
     * Merges the inputs from an InputFilter into the current one
     */
    public function merge(BaseInputFilter $inputFilter): static
    {
        foreach ($inputFilter->getInputs() as $name => $input) {
            $this->add($input, $name);
        }

        return $this;
    }

    /** @inheritDoc */
    public function getUnfilteredData(): array
    {
        return $this->unfilteredData;
    }

    /** @inheritDoc */
    public function setUnfilteredData(array $data): static
    {
        $this->unfilteredData = $data;
        return $this;
    }

    /** @psalm-assert-if-true int|non-empty-string $value */
    protected function isKey(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && $value !== '');
    }
}
