<?php

declare(strict_types=1);

namespace Phare\Collections\Exceptions;

use RuntimeException;

/**
 * Thrown by Collection::sole() when more than one item matches the given
 * criteria.
 */
class MultipleItemsFoundException extends RuntimeException
{
    public function __construct(public int $count = 0, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "{$count} items were found.");
    }
}
