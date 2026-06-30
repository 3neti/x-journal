<?php

namespace LBHurtado\XJournal\Exceptions;

use RuntimeException;

class JournalEntryIdempotencyConflictException extends RuntimeException
{
    public static function forEntryMismatch(string $idempotencyKey, string $existingReferenceNumber): self
    {
        return new self(
            "Execution journal idempotency conflict for key [{$idempotencyKey}]: existing entry [{$existingReferenceNumber}] payload differs."
        );
    }
}
