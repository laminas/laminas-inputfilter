<?php

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Validator\ValidatorChain;

interface InputInterface
{
    /**
     * @deprecated 2.4.8 Add Laminas\Validator\NotEmpty validator to the ValidatorChain and set this to `true`.
     *
     * @param bool $allowEmpty
     * @return $this
     */
    public function setAllowEmpty($allowEmpty);

    /**
     * @param bool $breakOnFailure
     * @return $this
     */
    public function setBreakOnFailure($breakOnFailure);

    /**
     * @param string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage($errorMessage);

    /**
     * @return $this
     */
    public function setFilterChain(FilterChain $filterChain);

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name);

    /**
     * @param bool $required
     * @return $this
     */
    public function setRequired($required);

    /**
     * @return $this
     */
    public function setValidatorChain(ValidatorChain $validatorChain);

    /**
     * @param mixed $value
     * @return $this
     */
    public function setValue($value);

    /**
     * @return $this
     */
    public function merge(InputInterface $input);

    /**
     * @deprecated 2.4.8 Add Laminas\Validator\NotEmpty validator to the ValidatorChain.
     *
     * @return bool
     */
    public function allowEmpty();

    /**
     * @return bool
     */
    public function breakOnFailure();

    /**
     * @return string|null
     */
    public function getErrorMessage();

    /**
     * @return FilterChain
     */
    public function getFilterChain();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return mixed
     */
    public function getRawValue();

    /**
     * @return bool
     */
    public function isRequired();

    /**
     * @return ValidatorChain
     */
    public function getValidatorChain();

    /**
     * @return mixed
     */
    public function getValue();

    /**
     * @return bool
     */
    public function isValid();

    /**
     * @return array<array-key, string>
     */
    public function getMessages();
}
