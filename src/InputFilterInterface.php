<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Countable;
use Laminas\Filter\FilterChain; // phpcs:ignore
use Laminas\Filter\FilterInterface; // phpcs:ignore
use Laminas\Validator\ValidatorChain; // phpcs:ignore
use Laminas\Validator\ValidatorInterface; // phpcs:ignore
use Traversable;

/**
 * @template TFilteredValues
 * @psalm-type FilterSpecification = array{
 *     name: string|class-string<FilterInterface>,
 *     priority?: int,
 *     options?: array<string, mixed>,
 * }
 * @psalm-type ValidatorSpecification = array{
 *     name: string|class-string<ValidatorInterface>,
 *     priority?: int,
 *     break_chain_on_failure?: bool,
 *     options?: array<string, mixed>,
 * }
 * @psalm-type InputSpecification = array{
 *     type?: string|class-string<InputFilterInterface>,
 *     name?: array-key,
 *     required?: bool,
 *     allow_empty?: bool,
 *     continue_if_empty?: bool,
 *     error_message?: string|null,
 *     fallback_value?: mixed|null,
 *     break_on_failure?: bool,
 *     filters?: FilterChain|iterable<array-key, FilterSpecification|callable|FilterInterface>,
 *     validators?: ValidatorChain|iterable<array-key, ValidatorSpecification|ValidatorInterface>,
 *     ...
 * }
 * @psalm-type InputFilterSpecification = array{
 *     type?: class-string<InputFilterInterface>|string,
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
    public const VALIDATE_ALL = 'INPUT_FILTER_ALL';

    /**
     * Add an input to the input filter
     *
     * @param  InputInterface|InputFilterInterface|InputSpecification|Traversable $input
     *     Implementations MUST handle at least one of the specified types, and
     *     raise an exception for any they cannot process.
     * @param  null|array-key $name Name used to retrieve this input
     * @return InputFilterInterface
     * @throws Exception\InvalidArgumentException If unable to handle the input type.
     */
    public function add($input, $name = null);

    /**
     * Retrieve a named input
     *
     * @param  array-key $name
     * @return InputInterface|InputFilterInterface
     */
    public function get($name);

    /**
     * Test if an input or input filter by the given name is attached
     *
     * @param  array-key $name
     * @return bool
     */
    public function has($name);

    /**
     * Remove a named input
     *
     * @param  array-key $name
     * @return InputFilterInterface
     */
    public function remove($name);

    /**
     * Set data to use when validating and filtering
     *
     * @param  iterable|null $data
     * @return InputFilterInterface
     */
    public function setData($data);

    /**
     * Is the data set valid?
     *
     * @return bool
     */
    public function isValid();

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
     * @param  array-key|list<array-key> $name
     * @return InputFilterInterface
     */
    public function setValidationGroup($name);

    /**
     * Return a list of inputs that were invalid.
     *
     * Implementations should return an associative array of name/input pairs
     * that failed validation.
     *
     * @return array<array-key, InputInterface|InputFilterInterface>
     */
    public function getInvalidInput();

    /**
     * Return a list of inputs that were valid.
     *
     * Implementations should return an associative array of name/input pairs
     * that passed validation.
     *
     * @return array<array-key, InputInterface|InputFilterInterface>
     */
    public function getValidInput();

    /**
     * Retrieve a value from a named input
     *
     * @param  array-key $name
     * @return mixed
     */
    public function getValue($name);

    /**
     * Return a list of filtered values
     *
     * List should be an associative array, with the values filtered. If
     * validation failed, this should raise an exception.
     *
     * @return array<array-key, mixed>
     * @psalm-return TFilteredValues
     */
    public function getValues();

    /**
     * Retrieve a raw (unfiltered) value from a named input
     *
     * @param  array-key $name
     * @return mixed
     */
    public function getRawValue($name);

    /**
     * Return a list of unfiltered values
     *
     * List should be an associative array of named input/value pairs,
     * with the values unfiltered.
     *
     * @return array<array-key, mixed>
     */
    public function getRawValues();

    /**
     * Return a list of validation failure messages
     *
     * Should return an associative array of named input/message list pairs.
     * Pairs should only be returned for inputs that failed validation.
     *
     * @return array<array-key, array<array-key, string|array>>
     */
    public function getMessages();
}
