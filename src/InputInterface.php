<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Validator\ValidatorChain;

/**
 * @category   Laminas
 * @package    Laminas_InputFilter
 */
interface InputInterface
{
    public function setAllowEmpty($allowEmpty);
    public function setBreakOnFailure($breakOnFailure);
    public function setErrorMessage($errorMessage);
    public function setFilterChain(FilterChain $filterChain);
    public function setName($name);
    public function setRequired($required);
    public function setValidatorChain(ValidatorChain $validatorChain);
    public function setValue($value);
    public function merge(InputInterface $input);

    public function allowEmpty();
    public function breakOnFailure();
    public function getErrorMessage();
    public function getFilterChain();
    public function getName();
    public function getRawValue();
    public function isRequired();
    public function getValidatorChain();
    public function getValue();

    public function isValid();
    public function getMessages();
}
