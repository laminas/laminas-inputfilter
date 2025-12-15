<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Countable;
use Laminas\Filter\FilterChain; // phpcs:ignore
use Laminas\Filter\FilterInterface; // phpcs:ignore
use Laminas\InputFilter\Exception\InputNotFoundException;
use Laminas\Validator\ValidatorChain; // phpcs:ignore
use Laminas\Validator\ValidatorInterface; // phpcs:ignore

/**
 * @template TFilteredValues
 * @psalm-import-type FilterSpecification from FilterChain
 * @psalm-import-type ValidatorSpecification from ValidatorChain
 * @psalm-type InputSpecification = array{
 *     type?: string|class-string<InputFilterInterface>,
 *     name?: array-key,
 *     required?: bool,
 *     allow_empty?: bool,
 *     continue_if_empty?: bool,
 *     error_message?: string|null,
 *     fallback_value?: mixed|null,
 *     break_on_failure?: bool,
 *     filters?: FilterChain|iterable<array-key, FilterSpecification|(callable(mixed):mixed)|FilterInterface>,
 *     validators?: ValidatorChain|iterable<array-key, ValidatorSpecification|ValidatorInterface>,
 * }
 * @psalm-type InputFilterSpecification = array{
 *     type: class-string<InputFilterInterface>|string,
 * }&array<array-key, InputSpecification|InputFilterInterface|InputInterface>
 * @psalm-type CollectionSpecification = array{
 *     type?: class-string<InputFilterInterface>|string,
 *     input_filter?: InputFilterSpecification|InputFilterInterface,
 *     count?: int,
 *     required?: bool,
 *     required_message?: string,
 * }&array<array-key, InputSpecification>
 * @psalm-type InputErrorMessages = array<string, string>
 * @psalm-type InputFilterErrorMessages = array<array-key, InputErrorMessages|InputErrorMessages[]>
 */
interface InputFilterInterface extends Countable
{
    public const VALIDATE_ALL = 'INPUT_FILTER_ALL';

    /**
     * Add an input to the input filter
     *
     * @param  InputInterface|InputFilterInterface|InputSpecification|InputFilterSpecification $input
     *     Implementations MUST handle at least one of the specified types, and
     *     raise an exception for any they cannot process.
     * @param  null|array-key $name Name used to retrieve this input
     * @throws Exception\InvalidArgumentException If unable to handle the input type.
     */
    public function add(
        InputInterface|InputFilterInterface|array $input,
        int|string|null $name = null,
    ): static;

    /**
     * Retrieve a named input
     *
     * @throws InputNotFoundException
     */
    public function get(int|string $name): InputInterface|InputFilterInterface;

    /**
     * Test if an input or input filter by the given name is attached
     */
    public function has(int|string $name): bool;

    /**
     * Remove a named input
     */
    public function remove(int|string $name): static;

    /**
     * Set data to use when validating and filtering
     *
     * @param  iterable<array-key, mixed>|null $data
     */
    public function setData(iterable|null $data): static;

    /**
     * Is the data set valid?
     *
     * @param array<array-key, mixed>|null $context
     */
    public function isValid(array|null $context = null): bool;

    /**
     * Provide a list of one or more elements indicating the complete set to validate
     *
     * When provided, calls to {@link isValid()} will only validate the provided set.
     *
     * If the initial value is {@link VALIDATE_ALL}, the current validation group, if
     * any, should be cleared.
     *
     * Implementations should allow passing a single array value, or multiple arguments,
     * each specifying a single input.
     *
     * @param array-key|array<array-key, mixed> $name
     * @throws InputNotFoundException
     */
    public function setValidationGroup(int|string|array $name): static;

    /**
     * Return a list of inputs that were invalid.
     *
     * Implementations should return an associative array of name/input pairs
     * that failed validation.
     *
     * @return array<array-key, InputInterface|InputFilterInterface>
     */
    public function getInvalidInput(): array;

    /**
     * Return a list of inputs that were valid.
     *
     * Implementations should return an associative array of name/input pairs
     * that passed validation.
     *
     * @return array<array-key, InputInterface|InputFilterInterface>
     */
    public function getValidInput(): array;

    /**
     * Retrieve a value from a named input
     */
    public function getValue(int|string $name): mixed;

    /**
     * Return a list of filtered values
     *
     * List should be an associative array, with the values filtered. If
     * validation failed, this should raise an exception.
     *
     * @return array<array-key, mixed>
     * @psalm-return TFilteredValues
     */
    public function getValues(): array;

    /**
     * Retrieve a raw (unfiltered) value from a named input
     *
     * @throws InputNotFoundException
     */
    public function getRawValue(int|string $name): mixed;

    /**
     * Return a list of unfiltered values
     *
     * List should be an associative array of named input/value pairs,
     * with the values unfiltered.
     *
     * @return array<array-key, mixed>
     */
    public function getRawValues(): array;

    /**
     * Return a list of validation failure messages
     *
     * Should return an associative array of named input/message list pairs.
     * Pairs should only be returned for inputs that failed validation.
     *
     * @return InputFilterErrorMessages|InputFilterErrorMessages[]
     */
    public function getMessages(): array;
}
