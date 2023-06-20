<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Translator\TranslatorInterface;
use Laminas\Validator\ValidatorChain;

use function class_exists;
use function is_array;

class Input implements
    InputInterface,
    EmptyContextInterface
{
    /** @var bool */
    protected $allowEmpty = false;

    /** @var bool */
    protected $continueIfEmpty = false;

    /** @var bool */
    protected $breakOnFailure = false;

    /** @var string|null */
    protected $errorMessage;

    /** @var null|FilterChain */
    protected $filterChain;

    /** @var null|string */
    protected $name;

    /** @var bool */
    protected $notEmptyValidator = false;

    /** @var bool */
    protected $required = true;

    /** @var null|ValidatorChain */
    protected $validatorChain;

    /** @var mixed */
    protected $value;

    /**
     * Flag for distinguish when $value contains the value previously set or the default one.
     *
     * @var bool
     */
    protected $hasValue = false;

    /** @var mixed|null */
    protected $fallbackValue;

    /** @var bool */
    protected $hasFallback = false;

    /** @param null|string $name */
    public function __construct($name = null)
    {
        $this->name = $name;
    }

    /**
     * @param  bool $allowEmpty
     * @return $this
     */
    public function setAllowEmpty($allowEmpty)
    {
        $this->allowEmpty = (bool) $allowEmpty;
        return $this;
    }

    /**
     * @param  bool $breakOnFailure
     * @return $this
     */
    public function setBreakOnFailure($breakOnFailure)
    {
        $this->breakOnFailure = (bool) $breakOnFailure;
        return $this;
    }

    /**
     * @param bool $continueIfEmpty
     * @return $this
     */
    public function setContinueIfEmpty($continueIfEmpty)
    {
        $this->continueIfEmpty = (bool) $continueIfEmpty;
        return $this;
    }

    /**
     * @param  string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = null === $errorMessage ? null : (string) $errorMessage;
        return $this;
    }

    /**
     * @return $this
     */
    public function setFilterChain(FilterChain $filterChain)
    {
        $this->filterChain = $filterChain;
        return $this;
    }

    /**
     * @param  string $name
     * @return $this
     */
    public function setName($name)
    {
        /** @psalm-suppress RedundantCastGivenDocblockType */
        $this->name = (string) $name;
        return $this;
    }

    /**
     * @param  bool $required
     * @return $this
     */
    public function setRequired($required)
    {
        /** @psalm-suppress RedundantCastGivenDocblockType */
        $this->required = (bool) $required;
        return $this;
    }

    /**
     * @return $this
     */
    public function setValidatorChain(ValidatorChain $validatorChain)
    {
        $this->validatorChain = $validatorChain;
        return $this;
    }

    /**
     * Set the input value.
     *
     * If you want to remove/unset the current value use {@link Input::resetValue()}.
     *
     * @see Input::getValue() For retrieve the input value.
     * @see Input::hasValue() For to know if input value was set.
     * @see Input::resetValue() For reset the input value to the default state.
     *
     * @param  mixed $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value    = $value;
        $this->hasValue = true;
        return $this;
    }

    /**
     * Reset input value to the default state.
     *
     * @see Input::hasValue() For to know if input value was set.
     * @see Input::setValue() For set a new value.
     *
     * @return $this
     */
    public function resetValue()
    {
        $this->value    = null;
        $this->hasValue = false;
        return $this;
    }

    /**
     * @param  mixed $value
     * @return $this
     */
    public function setFallbackValue($value)
    {
        $this->fallbackValue = $value;
        $this->hasFallback   = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function allowEmpty()
    {
        return $this->allowEmpty;
    }

    /**
     * @return bool
     */
    public function breakOnFailure()
    {
        return $this->breakOnFailure;
    }

    /**
     * @return bool
     */
    public function continueIfEmpty()
    {
        return $this->continueIfEmpty;
    }

    /**
     * @return string|null
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @return FilterChain
     */
    public function getFilterChain()
    {
        if (! $this->filterChain) {
            $this->filterChain = new FilterChain();
        }
        return $this->filterChain;
    }

    /**
     * @return null|string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getRawValue()
    {
        return $this->value;
    }

    /**
     * @return bool
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * @return ValidatorChain
     */
    public function getValidatorChain()
    {
        if (! $this->validatorChain) {
            $this->validatorChain = new ValidatorChain();
        }
        return $this->validatorChain;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        $filter = $this->getFilterChain();
        return $filter->filter($this->value);
    }

    /**
     * Flag for inform if input value was set.
     *
     * This flag used for distinguish when {@link Input::getValue()}
     * will return the value previously set or the default.
     *
     * @see Input::getValue() For retrieve the input value.
     * @see Input::setValue() For set a new value.
     * @see Input::resetValue() For reset the input value to the default state.
     *
     * @return bool
     */
    public function hasValue()
    {
        return $this->hasValue;
    }

    /**
     * @return mixed
     */
    public function getFallbackValue()
    {
        return $this->fallbackValue;
    }

    /**
     * @return bool
     */
    public function hasFallback()
    {
        return $this->hasFallback;
    }

    /** @return void */
    public function clearFallbackValue()
    {
        $this->hasFallback   = false;
        $this->fallbackValue = null;
    }

    /**
     * @return $this
     */
    public function merge(InputInterface $input)
    {
        $this->setBreakOnFailure($input->breakOnFailure());
        if ($input instanceof Input) {
            $this->setContinueIfEmpty($input->continueIfEmpty());
        }
        $this->setErrorMessage($input->getErrorMessage());
        $this->setName($input->getName());
        $this->setRequired($input->isRequired());
        $this->setAllowEmpty($input->allowEmpty());
        if (! $input instanceof Input || $input->hasValue()) {
            $this->setValue($input->getRawValue());
        }

        $filterChain = $input->getFilterChain();
        $this->getFilterChain()->merge($filterChain);

        $validatorChain = $input->getValidatorChain();
        $this->getValidatorChain()->merge($validatorChain);
        return $this;
    }

    /**
     * @param  mixed $context Extra "context" to provide the validator
     * @return bool
     */
    public function isValid($context = null)
    {
        if (is_array($this->errorMessage)) {
            $this->errorMessage = null;
        }

        $value           = $this->getValue();
        $hasValue        = $this->hasValue();
        $empty           = $value === null || $value === '' || $value === [];
        $required        = $this->isRequired();
        $allowEmpty      = $this->allowEmpty();
        $continueIfEmpty = $this->continueIfEmpty();

        if (! $hasValue && $this->hasFallback()) {
            $this->setValue($this->getFallbackValue());
            return true;
        }

        if (! $hasValue && ! $required) {
            return true;
        }

        if (! $hasValue) { // required, but no value
            if ($this->errorMessage === null) {
                $this->errorMessage = $this->prepareRequiredValidationFailureMessage();
            }
            return false;
        }

        if ($empty && ! $required && ! $continueIfEmpty) {
            return true;
        }

        if ($empty && $allowEmpty && ! $continueIfEmpty) {
            return true;
        }

        // At this point, we need to run validators.
        // If we do not allow empty and the "continue if empty" flag are
        // BOTH false, we inject the "not empty" validator into the chain,
        // which adds that logic into the validation routine.
        if (! $allowEmpty && ! $continueIfEmpty) {
            $this->injectNotEmptyValidator();
        }

        $validator = $this->getValidatorChain();
        $result    = $validator->isValid($value, $context);
        if (! $result && $this->hasFallback()) {
            $this->setValue($this->getFallbackValue());
            $result = true;
        }

        return $result;
    }

    /**
     * @return array<array-key, string>
     */
    public function getMessages()
    {
        if (null !== $this->errorMessage) {
            return (array) $this->errorMessage;
        }

        if ($this->hasFallback()) {
            return [];
        }

        $validator = $this->getValidatorChain();
        return $validator->getMessages();
    }

    /**
     * @return void
     */
    protected function injectNotEmptyValidator()
    {
        if ((! $this->isRequired() && $this->allowEmpty()) || $this->notEmptyValidator) {
            return;
        }
        $chain = $this->getValidatorChain();

        // Check if NotEmpty validator is already in chain
        $validators = $chain->getValidators();
        foreach ($validators as $validator) {
            if ($validator['instance'] instanceof NotEmpty) {
                $this->notEmptyValidator = true;
                return;
            }
        }

        $this->notEmptyValidator = true;

        if (class_exists(AbstractPluginManager::class)) {
            $chain->prependByName(NotEmpty::class, [], true);

            return;
        }

        $chain->prependValidator(new NotEmpty(), true);
    }

    /**
     * Create and return the validation failure message for required input.
     *
     * @return array<string, string>
     */
    protected function prepareRequiredValidationFailureMessage()
    {
        $chain    = $this->getValidatorChain();
        $notEmpty = $chain->plugin(NotEmpty::class);

        foreach ($chain->getValidators() as $validator) {
            if ($validator['instance'] instanceof NotEmpty) {
                $notEmpty = $validator['instance'];
                break;
            }
        }

        /** @psalm-var array<string, string> $templates */
        $templates  = $notEmpty->getOption('messageTemplates');
        $message    = $templates[NotEmpty::IS_EMPTY];
        $translator = $notEmpty->getTranslator();

        if ($translator instanceof TranslatorInterface) {
            $message = $translator->translate($message, $notEmpty->getTranslatorTextDomain());
        }

        return [
            NotEmpty::IS_EMPTY => $message,
        ];
    }
}
