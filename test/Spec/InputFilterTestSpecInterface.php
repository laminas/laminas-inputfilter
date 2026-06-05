<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\Spec;

use Laminas\InputFilter\InputFilterInterface;

/**
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 * @psalm-consistent-constructor
 */
interface InputFilterTestSpecInterface
{
    /**
     * This constructor is interfaced to ensure implementations can be 'newed' without arguments
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct();

    /** @return InputFilterSpecification */
    public function spec(): array;

    /** @return array<string, Expectation> */
    public function expectations(): array;
}
