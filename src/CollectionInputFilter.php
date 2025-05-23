<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Validator\NotEmpty;
use Traversable;

use function count;
use function get_debug_type;
use function is_array;
use function is_iterable;
use function sprintf;

/**
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 * @template TFilteredValues
 * @extends InputFilter<TFilteredValues>
 */
class CollectionInputFilter extends InputFilter
{
    protected bool $isRequired = false;

    protected ?int $count;

    /** @var array<array-key, array> */
    protected array $collectionValues = [];

    /** @var array<array-key, array> */
    protected array $collectionRawValues = [];

    /** @var array<array-key, array<string, array<array-key, string>>> */
    protected array $collectionMessages = [];

    protected ?BaseInputFilter $inputFilter;

    protected ?NotEmpty $notEmptyValidator;

    /**
     * Set the input filter to use when looping the data
     *
     * @param BaseInputFilter|InputFilterSpecification|Traversable $inputFilter
     * @return CollectionInputFilter
     * @throws Exception\RuntimeException
     */
    public function setInputFilter(BaseInputFilter|iterable $inputFilter): static
    {
        if (is_iterable($inputFilter)) {
            $inputFilter = $this->getFactory()->createInputFilter($inputFilter);
        }

        /** @psalm-suppress RedundantConditionGivenDocblockType, DocblockTypeContradiction */
        if (! $inputFilter instanceof BaseInputFilter) {
            throw new Exception\RuntimeException(sprintf(
                '%s expects an instance of %s; received "%s"',
                __METHOD__,
                BaseInputFilter::class,
                get_debug_type($inputFilter)
            ));
        }

        $this->inputFilter = $inputFilter;

        return $this;
    }

    /**
     * Get the input filter used when looping the data
     */
    public function getInputFilter(): BaseInputFilter
    {
        if (null === $this->inputFilter) {
            $this->inputFilter = new InputFilter();
        }

        return $this->inputFilter;
    }

    /**
     * Set if the collection can be empty
     *
     * @return $this
     */
    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;

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
     *
     * @return CollectionInputFilter
     */
    public function setCount(int $count): static
    {
        $this->count = $count > 0 ? $count : 0;

        return $this;
    }

    /**
     * Get the count of data to validate, use the count of data by default
     */
    public function getCount(): ?int
    {
        if (null === $this->count) {
            return $this->data !== null ? count($this->data) : 0;
        }

        return $this->count;
    }

    /**
     * @param iterable|null $data
     * @return $this
     */
    public function setData(?iterable $data): InputFilterInterface
    {
        /** @psalm-suppress DocblockTypeContradiction, RedundantConditionGivenDocblockType */
        if (! is_array($data) && ! $data instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable collection; invalid collection of type %s provided',
                __METHOD__,
                get_debug_type($data)
            ));
        }

        $this->setUnfilteredData($data);

        /** @psalm-suppress MixedAssignment */
        foreach ($data as $item) {
            /** @psalm-suppress RedundantConditionGivenDocblockType, DocblockTypeContradiction */
            if (is_iterable($item)) {
                continue;
            }

            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects each item in a collection to be an array or Traversable; '
                . 'invalid item in collection of type %s detected',
                __METHOD__,
                get_debug_type($item)
            ));
        }

        /** @psalm-suppress InvalidPropertyAssignmentValue */
        $this->data = $data;
        return $this;
    }

    /**
     * Retrieve the NotEmpty validator to use for failed "required" validations.
     *
     * This validator will be used to produce a validation failure message in
     * cases where the collection is empty but required.
     */
    public function getNotEmptyValidator(): NotEmpty
    {
        if ($this->notEmptyValidator === null) {
            $this->notEmptyValidator = new NotEmpty();
        }

        return $this->notEmptyValidator;
    }

    /**
     * Set the NotEmpty validator to use for failed "required" validations.
     *
     * This validator will be used to produce a validation failure message in
     * cases where the collection is empty but required.
     *
     * @return $this
     */
    public function setNotEmptyValidator(NotEmpty $notEmptyValidator): static
    {
        $this->notEmptyValidator = $notEmptyValidator;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isValid(mixed $context = null): bool
    {
        $this->collectionMessages = [];
        $inputFilter              = $this->getInputFilter();
        $valid                    = true;

        if ($this->getCount() < 1 && $this->isRequired) {
            $this->collectionMessages[] = $this->prepareRequiredValidationFailureMessage();
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

        /** @psalm-suppress MixedAssignment */
        foreach ($this->data as $key => $data) {
            /** @psalm-suppress MixedArgument */
            $inputFilter->setData($data);

            if (null !== $this->validationGroup) {
                $inputFilter->setValidationGroup($this->validationGroup[$key]);
            }

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

    /**
     * @return $this
     */
    public function setValidationGroup(array|int|string $name): static
    {
        if ($name === self::VALIDATE_ALL) {
            $name = null;
        }
        $this->validationGroup = $name;

        return $this;
    }

    /**
     * @return array<array-key, array>
     * @psalm-return TFilteredValues
     */
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
     *
     * @return array[]
     */
    public function clearValues(): array
    {
        return $this->collectionValues = [];
    }

    /**
     * Clear collectionRawValues
     *
     * @return array[]
     */
    public function clearRawValues(): array
    {
        return $this->collectionRawValues = [];
    }

    /**
     * @return array<array-key, array<string, array<array-key, string>>>
     */
    public function getMessages(): array
    {
        return $this->collectionMessages;
    }

    /**
     * {@inheritdoc}
     */
    public function getUnknown(): array
    {
        if ($this->data === null) {
            throw new Exception\RuntimeException(sprintf(
                '%s: no data present!',
                __METHOD__
            ));
        }

        $inputFilter = $this->getInputFilter();

        $unknownInputs = [];
        foreach ($this->data as $key => $data) {
            $inputFilter->setData($data);

            if ($unknown = $inputFilter->getUnknown()) {
                $unknownInputs[$key] = $unknown;
            }
        }

        return $unknownInputs;
    }

    protected function prepareRequiredValidationFailureMessage(): ?string
    {
        $notEmptyValidator = $this->getNotEmptyValidator();
        /** @var array<string, string> $templates */
        $templates  = $notEmptyValidator->getOption('messageTemplates');
        $message    = $templates[NotEmpty::IS_EMPTY];
        $translator = $notEmptyValidator->getTranslator();

        return [
            NotEmpty::IS_EMPTY => $translator
                ? $translator->translate($message, $notEmptyValidator->getTranslatorTextDomain())
                : $message,
        ];
    }
}
