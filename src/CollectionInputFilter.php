<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Validator\NotEmpty;
use NoDiscard;

use function assert;
use function count;
use function get_debug_type;
use function is_array;
use function is_iterable;
use function iterator_to_array;
use function max;
use function sprintf;

/**
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 * @template TFilteredValues
 * @extends InputFilter<array<array-key, TFilteredValues>>
 */
class CollectionInputFilter extends InputFilter
{
    protected bool $isRequired = false;
    protected int|null $count  = null;
    /** @var array<array-key, TFilteredValues> */
    protected array $collectionValues = [];
    /** @var array<array-key, array> */
    protected array $collectionRawValues = [];
    /** @var array<array-key, ErrorMessages> */
    protected array $collectionMessages = [];
    /** @var InputFilterInterface<TFilteredValues>|null */
    protected InputFilterInterface|null $inputFilter = null;
    private string|null $emptyErrorMessage           = null;

    /**
     * Data in a collection is guaranteed to be an array of arrays
     *
     * @psalm-suppress NonInvariantDocblockPropertyType
     * @var array<array-key, array<array-key, mixed>>|null
     */
    protected array|null $data = null;

    /**
     * In Collections, the type is not compatible with the parent class
     *
     * @psalm-suppress NonInvariantDocblockPropertyType
     * @var array<array-key, array<array-key, InputInterface|InputFilterInterface>>|null
     */
    protected array|null $invalidInputs = null;

    /**
     * In Collections, the type is not compatible with the parent class
     *
     * @psalm-suppress NonInvariantDocblockPropertyType
     * @var array<array-key, array<array-key, InputInterface|InputFilterInterface>>|null
     */
    protected array|null $validInputs = null;

    /**
     * Set the input filter to use when looping the data
     *
     * @param InputFilterInterface<TFilteredValues>|InputFilterSpecification|iterable $inputFilter
     */
    public function setInputFilter(InputFilterInterface|iterable $inputFilter): static
    {
        if (is_iterable($inputFilter)) {
            /** @psalm-var InputFilterInterface<TFilteredValues> $inputFilter */
            $inputFilter = $this->factory->createInputFilter($inputFilter);
        }

        $this->inputFilter = $inputFilter;

        return $this;
    }

    /**
     * Get the input filter used when looping the data
     *
     * @return InputFilterInterface<TFilteredValues>
     */
    public function getInputFilter(): InputFilterInterface
    {
        if (null === $this->inputFilter) {
            $this->inputFilter = new InputFilter($this->factory);
        }

        return $this->inputFilter;
    }

    /**
     * Set if the collection can be empty
     */
    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    /**
     * Set a custom error message for the collection being empty.
     * If not called, CollectionInputFilter will default to the NotEmpty validators IS_EMPTY message
     *
     * @param non-empty-string $message
     */
    public function setIsRequiredValidationMessage(string $message): static
    {
        $this->emptyErrorMessage = $message;

        return $this;
    }

    /**
     * Get if collection can be empty
     */
    public function getIsRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * Set the count of data to validate
     */
    public function setCount(int $count): static
    {
        $this->count = max($count, 0);

        return $this;
    }

    /**
     * Get the count of data to validate, use the count of data by default
     */
    public function getCount(): int
    {
        if (null === $this->count) {
            return $this->data !== null ? count($this->data) : 0;
        }

        return $this->count;
    }

    /** @inheritDoc */
    public function setData(iterable|null $data): static
    {
        $data = iterator_to_array($data ?? []);
        $this->setUnfilteredData($data);

        /** @psalm-var mixed $item */
        foreach ($data as $item) {
            if (is_array($item)) {
                continue;
            }

            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects each item in a collection to be an array or Traversable; '
                . 'invalid item in collection of type %s detected',
                __METHOD__,
                get_debug_type($item),
            ));
        }

        /**
         * Psalm cannot infer this from the previous scope
         *
         * @psalm-var array<array-key, array> $data
         */
        $this->data = $data;
        return $this;
    }

    /** @inheritDoc */
    public function isValid(array|null $context = null): bool
    {
        $this->collectionMessages = [];
        $inputFilter              = $this->getInputFilter();
        $valid                    = true;

        if ($this->getCount() < 1 && $this->isRequired) {
            $this->collectionMessages[] = $this->getEmptyValidationErrorMessages();
            $valid                      = false;
        }

        $dataCount = $this->data !== null ? count($this->data) : 0;
        if ($dataCount < $this->getCount()) {
            $valid = false;
        }

        if ($this->data === null) {
            $this->clearValues();
            $this->clearRawValues();

            return $valid;
        }

        foreach ($this->data as $key => $data) {
            assert($this->isKey($key));

            $inputFilter->setData($data);

            if ($this->validationGroup !== null && isset($this->validationGroup[$key])) {
                $inputFilter->setValidationGroup($this->validationGroup[$key]);
            }

            /**
             * @todo The current implementation will validate all sets using the same validation group if only
             *       the first item uses a validation group.
             *       For example, setValidationGroup([0 => 'fieldName']) will mean that only `fieldName` is validated
             *       for all items in the set.
             *       The group should be set to VALIDATE_ALL on each iteration, and modified on a per-key basis.
             */

            if ($inputFilter->isValid($context)) {
                $this->validInputs[$key] = $inputFilter->getValidInput();
            } else {
                $valid                          = false;
                $this->collectionMessages[$key] = $inputFilter->getMessages();
                $this->invalidInputs[$key]      = $inputFilter->getInvalidInput();
            }

            $this->collectionValues[$key]    = $inputFilter->getValues();
            $this->collectionRawValues[$key] = $inputFilter->getRawValues();
        }

        return $valid;
    }

    /** @inheritDoc */
    public function setValidationGroup(int|string|array $name): static
    {
        if ($name === self::VALIDATE_ALL) {
            $name = null;
        }
        $this->validationGroup = $name;

        return $this;
    }

    /** @return array<array-key, TFilteredValues> */
    public function getValues(): array
    {
        return $this->collectionValues;
    }

    /**
     * @return array<array-key, array>
     */
    public function getRawValues(): array
    {
        return $this->collectionRawValues;
    }

    /**
     * Clear collectionValues
     */
    public function clearValues(): void
    {
        $this->collectionValues = [];
    }

    /**
     * Clear collectionRawValues
     */
    public function clearRawValues(): void
    {
        $this->collectionRawValues = [];
    }

    public function getMessages(): ErrorMessages
    {
        return new ErrorMessages($this->collectionMessages);
    }

    /** @inheritDoc */
    public function getUnknown(): array
    {
        if ($this->data === null) {
            throw new Exception\RuntimeException(sprintf(
                '%s: no data present!',
                __METHOD__,
            ));
        }

        $inputFilter = $this->getInputFilter();
        if (! $inputFilter instanceof UnknownInputsCapableInterface) {
            return [];
        }

        $unknownInputs = [];
        foreach ($this->data as $key => $data) {
            $inputFilter->setData($data);
            $unknown = $inputFilter->getUnknown();

            if (count($unknown) > 0) {
                $unknownInputs[$key] = $unknown;
            }
        }

        return $unknownInputs;
    }

    private function getEmptyValidationErrorMessages(): ErrorMessages
    {
        $options = $this->emptyErrorMessage === null
            ? []
            : ['messages' => [NotEmpty::IS_EMPTY => $this->emptyErrorMessage]];

        $validator = $this->factory->getValidatorPluginManager()->build(NotEmpty::class, $options);

        $validator->isValid(null);

        return new ErrorMessages($validator->getMessages());
    }

    #[NoDiscard]
    public function validate(iterable $data, array $context = []): InputFilterValidationResult
    {
        $inputFilter = $this->getInputFilter();

        $data    = iterator_to_array($data, false); // Cast to a list, keys are not relevant
        $context = $context === [] ? $data : $context;

        $minCount = max(
            $this->isRequired ? 1 : 0,
            $this->count ?? 0,
        );

        $iterations = max(
            count($data),
            $minCount,
        );

        $results = [];
        for ($i = 0; $i < $iterations; $i++) {
            /** @psalm-var iterable<array-key, mixed> $set */
            $set = $data[$i] ?? [];
            /** @psalm-var ValidationResultInterface<TFilteredValues> $result */
            $result    = $inputFilter->validate($set, $context);
            $results[] = $result;
        }

        /**
         * This return type needs forcing, because it is effectively list<T>, but this class defines array<array-key, T>
         * as its generic type.
         *
         * @psalm-var InputFilterValidationResult<array<array-key, TFilteredValues>>
         */
        return new InputFilterValidationResult($results);
    }
}
