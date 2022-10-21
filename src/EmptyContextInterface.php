<?php

declare(strict_types=1);

namespace Laminas\InputFilter;

interface EmptyContextInterface
{
    /**
     * @param bool $continueIfEmpty
     * @return self
     */
    public function setContinueIfEmpty($continueIfEmpty);

    /**
     * @return bool
     */
    public function continueIfEmpty();
}
