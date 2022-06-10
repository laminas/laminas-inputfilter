<?php

namespace Laminas\InputFilter;

use Laminas\Validator\NotEmpty;
use Traversable;

use function count;
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use function sprintf;

/**
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 */
class CollectionInputFilter extends InputFilter
{
    /** @var bool */
    protected $isRequired = false;

    /** @var null|int */
    protected $count;

    /** @var array<array-key, array> */
    protected $collectionValues = [];

    /** @var array<array-key, array> */
    protected $collectionRawValues = [];

    /** @var array<array-key, array<string, array<array-key, string>>> */
    protected $collectionMessages = [];

    /** @var BaseInputFilter|null */
    protected $inputFilter;

    /** @var NotEmpty|null */
    protected $notEmptyValidator;

    /**
     * Set the input filter to use when looping the data
     *
     * @param BaseInputFilter|InputFilterSpecification|Traversable $inputFilter
     * @throws Exception\RuntimeException
     * @return CollectionInputFilter
     */
    public function setInputFilter($inputFilter)
    {
        if (is_array($inputFilter) || $inputFilter instanceof Traversable) {
            $inputFilter = $this->getFactory()->createInputFilter($inputFilter);
        }

        /** @psalm-suppress RedundantConditionGivenDocblockType, DocblockTypeContradiction */
        if (! $inputFilter instanceof BaseInputFilter) {
            throw new Exception\RuntimeException(sprintf(
                '%s expects an instance of %s; received "%s"',
                __METHOD__,
                BaseInputFilter::class,
                is_object($inputFilter) ? get_class($inputFilter) : gettype($inputFilter)
            ));
        }

        $this->inputFilter = $inputFilter;

        return $this;
    }

    /**
     * Get the input filter used when looping the data
     *
     * @return BaseInputFilter
     */
    public function getInputFilter()
    {
        if (null === $this->inputFilter) {
            $this->inputFilter = new InputFilter();
        }

        return $this->inputFilter;
    }

    /**
     * Set if the collection can be empty
     *
     * @param bool $isRequired
     * @return $this
     */
    public function setIsRequired($isRequired)
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    /**
     * Get if collection can be empty
     *
     * @return bool
     */
    public function getIsRequired()
    {
        return $this->isRequired;
    }

    /**
     * Set the count of data to validate
     *
     * @param int $count
     * @return CollectionInputFilter
     */
    public function setCount($count)
    {
        $this->count = $count > 0 ? $count : 0;

        return $this;
    }

    /**
     * Get the count of data to validate, use the count of data by default
     *
     * @return int
     */
    public function getCount()
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
    public function setData($data)
    {
        /** @psalm-suppress DocblockTypeContradiction, RedundantConditionGivenDocblockType */
        if (! is_array($data) && ! $data instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable collection; invalid collection of type %s provided',
                __METHOD__,
                is_object($data) ? get_class($data) : gettype($data)
            ));
        }

        $this->setUnfilteredData($data);

        /** @psalm-suppress MixedAssignment */
        foreach ($data as $item) {
            /** @psalm-suppress RedundantConditionGivenDocblockType, DocblockTypeContradiction */
            if (is_array($item) || $item instanceof Traversable) {
                continue;
            }

            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects each item in a collection to be an array or Traversable; '
                . 'invalid item in collection of type %s detected',
                __METHOD__,
                is_object($item) ? get_class($item) : gettype($item)
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
     *
     * @return NotEmpty
     */
    public function getNotEmptyValidator()
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
    public function setNotEmptyValidator(NotEmpty $notEmptyValidator)
    {
        $this->notEmptyValidator = $notEmptyValidator;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isValid($context = null)
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

        if (! $this->data) {
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
     * @param string|array<array-key, list<string>> $name
     * @return $this
     */
    public function setValidationGroup($name)
    {
        if ($name === self::VALIDATE_ALL) {
            $name = null;
        }
        $this->validationGroup = $name;

        return $this;
    }

    /**
     * @return array<array-key, array>
     */
    public function getValues()
    {
        return $this->collectionValues;
    }

    /**
     * @return array<array-key, array>
     */
    public function getRawValues()
    {
        return $this->collectionRawValues;
    }

    /**
     * Clear collectionValues
     *
     * @return array[]
     */
    public function clearValues()
    {
        return $this->collectionValues = [];
    }

    /**
     * Clear collectionRawValues
     *
     * @return array[]
     */
    public function clearRawValues()
    {
        return $this->collectionRawValues = [];
    }

    /**
     * @return array<array-key, array<string, array<array-key, string>>>
     */
    public function getMessages()
    {
        return $this->collectionMessages;
    }

    /**
     * {@inheritdoc}
     */
    public function getUnknown()
    {
        if (! $this->data) {
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

    /**
     * @return array<string, string>
     */
    protected function prepareRequiredValidationFailureMessage()
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
