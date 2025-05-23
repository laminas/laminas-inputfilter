<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\InputFilter\FileInput\FileInputDecoratorInterface;
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
class FileInput extends Input
{
    protected bool $isValid = false;

    protected bool $autoPrependUploadValidator = true;

    private ?FileInputDecoratorInterface $implementation = null;

    public function setValue(mixed $value): static
    {
        $this->implementation = $this->createDecoratorImplementation($value);
        parent::setValue($value);
        return $this;
    }

    public function resetValue(): static
    {
        $this->implementation = null;
        return parent::resetValue();
    }

    /**
     * @param bool $value Enable/Disable automatically prepending an Upload validator
     * @return $this
     */
    public function setAutoPrependUploadValidator(bool $value): static
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
        if ($this->implementation === null) {
            return $this->value;
        }
        return $this->implementation->getValue();
    }

    /**
     * Checks if the raw input value is an empty file input eg: no file was uploaded
     */
    public function isEmptyFile(mixed $rawValue): bool
    {
        if ($rawValue instanceof UploadedFileInterface) {
            return FileInput\PsrFileInputDecorator::isEmptyFileDecorator($rawValue);
        }

        if (is_array($rawValue)) {
            if (isset($rawValue[0]) && $rawValue[0] instanceof UploadedFileInterface) {
                return FileInput\PsrFileInputDecorator::isEmptyFileDecorator($rawValue);
            }

            return FileInput\HttpServerFileInputDecorator::isEmptyFileDecorator($rawValue);
        }

        return true;
    }

    /**
     * @param mixed|null $context Extra "context" to provide the validator
     */
    public function isValid(mixed $context = null): bool
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

        assert($this->implementation !== null);
        return $this->implementation->isValid($context);
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

    private function createDecoratorImplementation(mixed $value): FileInputDecoratorInterface
    {
        // Single PSR-7 instance
        if ($value instanceof UploadedFileInterface) {
            return new FileInput\PsrFileInputDecorator($this);
        }

        if (is_array($value)) {
            if (isset($value[0]) && $value[0] instanceof UploadedFileInterface) {
                // Array of PSR-7 instances
                return new FileInput\PsrFileInputDecorator($this);
            }

            // Single or multiple SAPI file upload arrays
            return new FileInput\HttpServerFileInputDecorator($this);
        }

        // AJAX/XHR/Fetch case
        return new FileInput\HttpServerFileInputDecorator($this);
    }
}
