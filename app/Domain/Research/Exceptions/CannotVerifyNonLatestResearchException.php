<?php

declare(strict_types=1);

namespace App\Domain\Research\Exceptions;

use RuntimeException;

/**
 * Guards the aggregate invariant that only the current (highest-version) brief for
 * a connection is the source of truth. Verifying a superseded/older brief would
 * stamp the connection's cadence from stale research and leave two "Complete"
 * briefs for one connection, so the action refuses it.
 */
final class CannotVerifyNonLatestResearchException extends RuntimeException
{
    public static function forConnection(int $connectionId): self
    {
        return new self(
            "This brief is not the latest for connection #{$connectionId}; only the current version can be marked verified."
        );
    }
}
