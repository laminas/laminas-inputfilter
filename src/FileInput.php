<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\InputFilter\FileInput\FileInputHandlerInterface;
use Laminas\Validator\File\UploadFile as UploadValidator;
use Laminas\Validator\ValidatorChain;
use Psr\Http\Message\UploadedFileInterface;

use function assert;
use function is_array;

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
    public function setValue($value): static
    {
        $this->handler = $this->createHandler($value);
        parent::setValue($value);
        return $this;
    }

    /** @return $this */
    public function resetValue(): static
    {
        $this->handler = null;
        return parent::resetValue();
    }

    /**
     * @param  bool $value Enable/Disable automatically prepending an Upload validator
     * @return $this
     */
    public function setAutoPrependUploadValidator($value): self
    {
        $this->autoPrependUploadValidator = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAutoPrependUploadValidator()
    {
        return $this->autoPrependUploadValidator;
    }

    /**
     * @return mixed
     */
    public function getValue()
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
    public function isValid(?array $context = null): bool
    {
        $rawValue        = $this->getRawValue();
        $hasValue        = $this->hasValue();
        $empty           = $this->isEmptyFile($rawValue);
        $required        = $this->isRequired();
        $allowEmpty      = $this->allowEmpty();
        $continueIfEmpty = $this->continueIfEmpty();

        if (! $hasValue && ! $required) {
            return true;
        }

        if (! $hasValue && ! $this->hasFallback()) { // required, no value, and no fallback
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

        assert($this->handler !== null);
        $this->isValid = $this->handler->isValid(
            $rawValue,
            $this->injectUploadValidator($this->getValidatorChain()),
            $context
        );
        return $this->isValid;
    }

    /**
     * @return $this
     */
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

    private function injectUploadValidator(ValidatorChain $chain): ValidatorChain
    {
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
