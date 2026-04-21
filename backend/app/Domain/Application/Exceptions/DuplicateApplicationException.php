<?php

declare(strict_types=1);

namespace App\Domain\Application\Exceptions;

use DomainException;

final class DuplicateApplicationException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You have already applied to this job.');
    }
}
