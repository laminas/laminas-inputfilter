<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\InputFilter\FileInput\FileInputHandlerInterface;
use Laminas\Validator\File\UploadFile as UploadValidator;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorChainInterface;
use NoDiscard;
use Psr\Http\Message\UploadedFileInterface;

use function array_merge;
use function assert;
use function is_array;
use function sprintf;

/**
 * FileInput is a special Input type for handling uploaded files.
 *
 * It differs from Input in a few ways:
 *
 * 1. It expects the raw value to either be in the $_FILES array format, or an
 *    array of PSR-7 UploadedFileInterface instances.
 *
 * 2. The validators are run **before** the filters (the opposite behavior of Input).
 *    This is so validation can be run prior to any filters that may
 *    rename/move/modify the file.
 *
 * 3. Instead of adding a NotEmpty validator, it will (by default) automatically add
 *    a Laminas\Validator\File\Upload validator.
 */
final class FileInput extends Input
{
    private bool $isValid                       = false;
    private bool $autoPrependUploadValidator    = true;
    private ?FileInputHandlerInterface $handler = null;

    /**
     * @inheritDoc
     * @param array|UploadedFileInterface $value
     */
    public function setValue(mixed $value): static
    {
        $this->handler = $this->createHandler($value);
        parent::setValue($value);
        return $this;
    }

    public function resetValue(): static
    {
        $this->handler = null;
        return parent::resetValue();
    }

    /**
     * @param  bool $value Enable/Disable automatically prepending an Upload validator
     * @return $this
     */
    public function setAutoPrependUploadValidator(bool $value): self
    {
        $this->autoPrependUploadValidator = $value;
        return $this;
    }

    public function getAutoPrependUploadValidator(): bool
    {
        return $this->autoPrependUploadValidator;
    }

    public function getValue(): mixed
    {
        if ($this->handler === null) {
            return $this->value;
        }
        return $this->handler->filterValue($this->value, $this->isValid, $this->getFilterChain());
    }

    /**
     * Checks if the raw input value is an empty file input eg: no file was uploaded
     *
     * @param mixed $rawValue
     * @return bool
     */
    public function isEmptyFile($rawValue)
    {
        if ($rawValue instanceof UploadedFileInterface) {
            return FileInput\PsrFileInputHandler::isEmptyFile($rawValue);
        }

        if (is_array($rawValue)) {
            if (isset($rawValue[0]) && $rawValue[0] instanceof UploadedFileInterface) {
                return FileInput\PsrFileInputHandler::isEmptyFile($rawValue);
            }

            return FileInput\HttpServerFileInputHandler::isEmptyFile($rawValue);
        }

        return true;
    }

    /** @inheritDoc */
    public function isValid(array|null $context = null): bool
    {
        $empty = $this->isEmptyFile($this->value);

        if (! $this->hasValue && ! $this->required) {
            return true;
        }

        if (! $this->hasValue && ! $this->hasFallback()) { // required, no value, and no fallback
            if ($this->errorMessage === null) {
                $this->errorMessage = $this->prepareRequiredValidationFailureMessage();
            }
            return false;
        }

        if ($empty && ! $this->required && ! $this->continueIfEmpty) {
            return true;
        }

        if ($empty && $this->allowEmpty && ! $this->continueIfEmpty) {
            return true;
        }

        assert($this->handler !== null);
        $this->isValid = $this->handler->isValid(
            $this->value,
            $this->injectUploadValidator($this->getValidatorChain()),
            $context,
        );
        return $this->isValid;
    }

    #[NoDiscard]
    public function validate(mixed $value, array $context): InputValidationResult
    {
        $isEmpty = $value === '' || $value === [] || $value === null || $this->isEmptyFile($value);
        $handler = $this->createHandler($value);

        if (
            // We have a valid result when a value is empty, but a fallback is present
            ($isEmpty && $this->hasFallback)
            ||
            // Empty values are valid when they are not required and validation should not continue for empty values
            ($isEmpty && ! $this->required && ! $this->continueIfEmpty)
            ||
            // Empty is valid when allowEmpty is true and continue if empty is false
            ($isEmpty && $this->allowEmpty && ! $this->continueIfEmpty)
        ) {
            return InputValidationResult::pass(
                $this->name,
                $value,
                $handler->filterValue($value, true, $this->filterChain),
            );
        }

        $validatorChain = $this->injectUploadValidator($this->validatorChain);

        $isValid  = $handler->isValid(
            $value,
            $validatorChain,
            $context,
        );
        $messages = $this->validatorChain->getMessages();

        /**
         * An empty value should not be considered valid in this situation, regardless
         * of what the validator chain says.
         * Instead of mutating the chain, fail validation with a validation failure message that advises the user to
         * customise the validation chain with a NotEmpty validator.
         */
        if ($isValid && $isEmpty) {
            $isValid  = false;
            $messages = array_merge([
                InputInterface::EMPTY_FAILURE_VALIDATION_KEY => sprintf(
                    'The value for "%s" was empty, but its configuration prohibits an empty value. '
                    . 'Enable the auto-prepend of a "UploadFile" validator to this input’s chain, '
                    . 'or configure one manually in order to customise '
                    . 'this validation failure message',
                    $this->name,
                ),
            ], $messages);
        }

        return $isValid
            ? InputValidationResult::pass(
                $this->name,
                $value,
                $handler->filterValue($value, true, $this->filterChain),
            )
            : InputValidationResult::fail(
                $this->name,
                $value,
                $handler->filterValue($value, false, $this->filterChain),
                new ErrorMessages($messages),
            );
    }

    public function merge(InputInterface $input): static
    {
        parent::merge($input);
        if ($input instanceof FileInput) {
            $this->setAutoPrependUploadValidator($input->getAutoPrependUploadValidator());
        }
        return $this;
    }

    /**
     * No-op, NotEmpty validator does not apply for FileInputs.
     * See also: BaseInputFilter::isValid()
     */
    protected function injectNotEmptyValidator(): void
    {
        $this->notEmptyValidator = true;
    }

    private function createHandler(mixed $value): FileInputHandlerInterface
    {
        // Single PSR-7 instance
        if ($value instanceof UploadedFileInterface) {
            return new FileInput\PsrFileInputHandler();
        }

        if (is_array($value)) {
            if (isset($value[0]) && $value[0] instanceof UploadedFileInterface) {
                // Array of PSR-7 instances
                return new FileInput\PsrFileInputHandler();
            }

            // Single or multiple SAPI file upload arrays
            return new FileInput\HttpServerFileInputHandler();
        }

        // AJAX/XHR/Fetch case
        return new FileInput\HttpServerFileInputHandler();
    }

    private function injectUploadValidator(ValidatorChainInterface $chain): ValidatorChain
    {
        assert($chain instanceof ValidatorChain);

        if (! $this->autoPrependUploadValidator) {
            return $chain;
        }

        // Check if Upload validator is already first in chain
        $validators = $chain->getValidators();
        if (
            isset($validators[0]['instance'])
            && $validators[0]['instance'] instanceof UploadValidator
        ) {
            $this->autoPrependUploadValidator = false;
            return $chain;
        }

        $chain->prependByName(UploadValidator::class, [], true);
        $this->autoPrependUploadValidator = false;

        return $chain;
    }
}
