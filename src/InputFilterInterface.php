<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Countable;
use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterInterface;
use Laminas\InputFilter\Exception\InputNotFoundException;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;
use NoDiscard;

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
 */
interface InputFilterInterface extends Countable
{
    /**
     * @deprecated Since 3.0 Validation groups are only available in the old validation API and will be removed in 4.0
     */
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
     * @deprecated Since 3.0. Please migrate to the {@link validate()} API which accepts input as an argument instead
     *             of mutating the internal state of the input filter.
     *
     * @param iterable<array-key, mixed>|null $data
     */
    public function setData(iterable|null $data): static;

    /**
     * Is the data set valid?
     *
     * @deprecated  Since 3.0. Please migrate to the {@link validate()} API which returns a result object instead of a
     *              boolean. This API will continue to work until its removal in 4.0
     *
     * @param array<array-key, mixed>|null $context
     */
    public function isValid(array|null $context = null): bool;

    /**
     * Validate a payload using the configured validator and filter chains
     *
     * This method performs stateless validation, returning a result value rather than a boolean. Calling validate()
     * does not mutate the internal state of the input filter, therefore it is safe to call multiple times for different
     * payloads.
     *
     * The result of this method should not be ignored. Calls to `isValid()` are irrelevant when using this api and the
     * result value returned encapsulates all validation information, filtered and unfiltered values.
     *
     * Note that validation groups are ignored and the entire data set is validated against all configured inputs.
     *
     * Before migrating to this method, please familiarise yourself with the migration guide for version 3.x
     *
     * @param iterable<array-key, mixed> $data
     * @param array<array-key, mixed> $context
     * @return InputFilterValidationResult<TFilteredValues>
     */
    #[NoDiscard]
    public function validate(iterable $data, array $context = []): InputFilterValidationResult;

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
     * @deprecated Since 3.0. Validation groups are deprecated entirely and will be removed in 4.0. They are still
     *             supported in the 'old' API, but are ignored by the new stateless API when using {@link validate()}.
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
     * @deprecated Since 3.0. This method is part of the old API and will be removed in 4.0
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
     * @deprecated Since 3.0. This method is part of the old API and will be removed in 4.0
     *
     * @return array<array-key, InputInterface|InputFilterInterface>
     */
    public function getValidInput(): array;

    /**
     * Retrieve a value from a named input
     *
     * @deprecated Since 3.0. This method is part of the old API and will be removed in 4.0
     *             {@link InputFilterValidationResult::resultFor()} can yield the result for a specific input enabling
     *             retrieval of specific values.
     */
    public function getValue(int|string $name): mixed;

    /**
     * Return a list of filtered values
     *
     * List should be an associative array, with the values filtered. If
     * validation failed, this should raise an exception.
     *
     * @deprecated Since 3.0. Using the rsult returned from {@link validate()}, you can retrieve the filtered values
     *             by calling {@link ValidationResultInterface::value()}
     *
     * @return array<array-key, mixed>
     * @psalm-return TFilteredValues
     */
    public function getValues(): array;

    /**
     * Retrieve a raw (unfiltered) value from a named input
     *
     * @deprecated Since 3.0. Using the rsult returned from {@link validate()}, you can retrieve the un-filtered values
     *             by calling {@link ValidationResultInterface::rawValue()}
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
     * @deprecated Since 3.0. Using the rsult returned from {@link validate()}, you can retrieve the un-filtered values
     *             by calling {@link ValidationResultInterface::rawValue()}
     *
     * @return array<array-key, mixed>
     */
    public function getRawValues(): array;

    /**
     * Return validation failure messages
     *
     * @deprecated Since 3.0. Error messages can be retrieved from the immutable result returned by {@link validate()}
     *             by calling {@link ValidationResultInterface::getMessages()}
     */
    public function getMessages(): ErrorMessages;
}
