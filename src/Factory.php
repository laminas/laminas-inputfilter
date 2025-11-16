<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterChainInterface;
use Laminas\Filter\FilterInterface;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\Exception\RuntimeException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorChainInterface;
use Laminas\Validator\ValidatorPluginManager;
use Psr\Container\ContainerInterface;
use Traversable;

use function assert;
use function class_exists;
use function get_debug_type;
use function in_array;
use function is_a;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function sprintf;

/**
 * @psalm-import-type InputSpecification from InputFilterInterface
 * @psalm-import-type FilterSpecification from FilterChain
 * @psalm-import-type ValidatorSpecification from ValidatorChain
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 * @psalm-import-type CollectionSpecification from InputFilterInterface
 */
final class Factory
{
    public static function new(ContainerInterface|null $container = null): self
    {
        $container = $container ?? new ServiceManager();

        if ($container->has(self::class)) {
            return $container->get(self::class);
        }

        $factory = new Factory(
            $container->has(FilterPluginManager::class)
            ? $container->get(FilterPluginManager::class)
            : new FilterPluginManager($container),
            $container->has(ValidatorPluginManager::class)
            ? $container->get(ValidatorPluginManager::class)
            : new ValidatorPluginManager($container),
            $container->has(InputFilterPluginManager::class)
            ? $container->get(InputFilterPluginManager::class)
            : new InputFilterPluginManager($container),
        );

        if ($container instanceof ServiceManager) {
            $container->setService(self::class, $factory);
        }

        return $factory;
    }

    public function __construct(
        private readonly FilterPluginManager $filterPluginManager,
        private readonly ValidatorPluginManager $validatorPluginManager,
        private readonly InputFilterPluginManager $inputFilterPluginManager,
    ) {
    }

    /**
     * @param class-string $class
     * @psalm-assert-if-true class-string<Input>|class-string<ArrayInput>|class-string<FileInput> $class
     */
    private function canCreateInputType(string $class): bool
    {
        return in_array(
            $class,
            [
                Input::class,
                ArrayInput::class,
                FileInput::class,
            ],
            true,
        );
    }

    /**
     * @param InputSpecification $spec
     * @return array{
     *     filterChain: FilterChainInterface,
     *     validatorChain: ValidatorChainInterface,
     * }
     * @throws InvalidArgumentException
     */
    private function buildChainsFromSpecification(array $spec): array
    {
        $filters    = $spec['filters'] ?? [];
        $validators = $spec['validators'] ?? [];

        if (! is_array($filters) && ! is_callable($filters) && ! $filters instanceof FilterInterface) {
            throw new InvalidArgumentException("filters must be an array, callable, or FilterInterface. Received: "
                . get_debug_type($filters));
        }

        if (is_array($filters)) {
            FilterChain::validateSpecification(['filters' => $filters]);
        }

        if (is_array($validators)) {
            ValidatorChain::validateSpecification($validators);
        }

        return [
            'filterChain'    => $filters instanceof FilterChainInterface
                ? $filters
                : $this->filterPluginManager->build(FilterChain::class, ['filters' => $filters]),
            'validatorChain' => $validators instanceof ValidatorChainInterface
                ? $validators
                : $this->validatorPluginManager->build(ValidatorChain::class, $validators),
        ];
    }

    /**
     * Factory for input objects
     *
     * @param InputSpecification|InputProviderInterface $inputSpecification
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function createInput(array|InputProviderInterface $inputSpecification): InputInterface
    {
        $spec = $inputSpecification instanceof InputProviderInterface
            ? $inputSpecification->getInputSpecification()
            : $inputSpecification;

        $class = $spec['type'] ?? Input::class;

        /** @var mixed|null $customInput */
        $customInput = $this->inputFilterPluginManager->has($class)
            ? $this->inputFilterPluginManager->get($class)
            : null;

        if ($customInput === null && ! class_exists($class)) {
            throw new RuntimeException(sprintf(
                'Input factory expects the "type" to be a valid class or a plugin name; received "%s"',
                $class,
            ));
        }

        if ($customInput === null && ! $this->canCreateInputType($class)) {
            throw new RuntimeException(sprintf(
                'Only internal input types "Input", "ArrayInput" and "FileInput" can be created by the factory. '
                . 'You will need to create your own factory for custom inputs because we cannot know what your '
                . 'constructor arguments might be. Received the type: "%s"',
                $class,
            ));
        }

        if ($customInput === null) {
            [
                'filterChain'    => $filterChain,
                'validatorChain' => $validatorChain,
            ] = $this->buildChainsFromSpecification($spec);

            /** @psalm-suppress UnsafeInstantiation */
            $input = new $class($filterChain, $validatorChain);
        } else {
            /** @var mixed $input */
            $input = $customInput;
        }

        if (! $input instanceof InputInterface) {
            throw new RuntimeException(sprintf(
                'Input factory expects the "type" to be a class implementing %s; received "%s"',
                InputInterface::class,
                get_debug_type($input),
            ));
        }

        $this->applyInputOptions($input, $spec);

        return $input;
    }

    /** @param InputSpecification $spec */
    private function applyInputOptions(InputInterface $input, array $spec): void
    {
        if (isset($spec['name'])) {
            $input->setName($spec['name']);
        }

        if (isset($spec['required'])) {
            $input->setRequired($spec['required']);
        }

        if (isset($spec['allow_empty'])) {
            $input->setAllowEmpty($spec['allow_empty']);
            if (! isset($spec['required'])) {
                $input->setRequired(! $spec['allow_empty']);
            }
        }

        if (isset($spec['continue_if_empty']) && $input instanceof Input) {
            $input->setContinueIfEmpty($spec['continue_if_empty']);
        }

        if (isset($spec['error_message'])) {
            $input->setErrorMessage($spec['error_message']);
        }

        if (isset($spec['fallback_value']) && $input instanceof Input) {
            $input->setFallbackValue($spec['fallback_value']);
        }

        if (isset($spec['break_on_failure'])) {
            $input->setBreakOnFailure($spec['break_on_failure']);
        }
    }

    /**
     * @psalm-assert-if-true InputSpecification $spec
     */
    private function isInputSpecification(array $spec): bool
    {
        /** @var mixed $type */
        $type = $spec['type'] ?? null;

        if (is_string($type) && is_a($type, InputInterface::class, true)) {
            return true;
        }

        if (is_string($type) && is_a($type, InputFilterInterface::class, true)) {
            return false;
        }

        $keys = [
            'type',
            'name',
            'required',
            'allow_empty',
            'continue_if_empty',
            'error_message',
            'fallback_value',
            'break_on_failure',
            'filters',
            'validators',
        ];

        foreach ($keys as $key) {
            unset($spec[$key]);
        }

        return $spec === [];
    }

    /** @param InputSpecification|InputFilterSpecification $spec */
    public function create(array $spec): InputInterface|InputFilterInterface
    {
        if ($this->isInputSpecification($spec)) {
            return $this->createInput($spec);
        }

        /** @psalm-var InputFilterSpecification $spec */

        return $this->createInputFilter($spec);
    }

    /**
     * Factory for input filters
     *
     * phpcs:ignore Generic.Files.LineLength.TooLong, SlevomatCodingStandard.Commenting.DocCommentSpacing
     * @param InputFilterSpecification|CollectionSpecification|Traversable|InputFilterProviderInterface $inputFilterSpecification
     * @return InputFilterInterface
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function createInputFilter($inputFilterSpecification)
    {
        if ($inputFilterSpecification instanceof InputFilterProviderInterface) {
            $inputFilterSpecification = $inputFilterSpecification->getInputFilterSpecification();
        }

        if ($inputFilterSpecification instanceof Traversable) {
            $inputFilterSpecification = ArrayUtils::iteratorToArray($inputFilterSpecification);
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (! is_array($inputFilterSpecification)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                get_debug_type($inputFilterSpecification),
            ));
        }

        $type = InputFilter::class;

        if (isset($inputFilterSpecification['type']) && is_string($inputFilterSpecification['type'])) {
            $type = $inputFilterSpecification['type'];
            unset($inputFilterSpecification['type']);
        }

        $inputFilter = $this->inputFilterPluginManager->get($type);
        assert($inputFilter instanceof InputFilterInterface); // As opposed to InputInterface

        if ($inputFilter instanceof CollectionInputFilter) {
            if (isset($inputFilterSpecification['input_filter'])) {
                $inputFilter->setInputFilter($inputFilterSpecification['input_filter']);
            }
            if (isset($inputFilterSpecification['count'])) {
                $inputFilter->setCount($inputFilterSpecification['count']);
            }
            if (isset($inputFilterSpecification['required'])) {
                $inputFilter->setIsRequired($inputFilterSpecification['required']);
            }
            if (isset($inputFilterSpecification['required_message'])) {
                $inputFilter->setIsRequiredValidationMessage($inputFilterSpecification['required_message']);
            }
            return $inputFilter;
        }

        foreach ($inputFilterSpecification as $key => $value) {
            if (null === $value) {
                continue;
            }

            if (
                $value instanceof InputInterface
                || $value instanceof InputFilterInterface
            ) {
                $inputFilter->add($value, $key);
                continue;
            }

            assert(is_array($value));

            // Patch to enable nested, integer indexed input_filter_specs.
            // Check type and name are in spec, and that composed type is
            // an input filter...
            if (
                (isset($value['type']) && is_string($value['type']))
                && (isset($value['name']) && is_string($value['name']))
                && $this->inputFilterPluginManager->get($value['type']) instanceof InputFilter
            ) {
                // If $key is an integer, reset it to the specified name.
                if (is_int($key)) {
                    $key = $value['name'];
                }

                // Remove name from specification. InputFilter doesn't have a
                // name property!
                unset($value['name']);
            }

            $inputFilter->add($this->create($value), $key);
        }

        return $inputFilter;
    }

    public function getValidatorPluginManager(): ValidatorPluginManager
    {
        return $this->validatorPluginManager;
    }
}
