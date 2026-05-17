<?php

declare(strict_types=1);

namespace Phare\Collections\Exceptions;

use RuntimeException;

/**
 * Thrown by Collection::sole() when no item matches the given criteria.
 */
class ItemNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Item not found.')
    {
        parent::__construct($message);
    }
}
