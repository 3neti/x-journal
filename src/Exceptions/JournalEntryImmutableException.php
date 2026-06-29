<?php

namespace LBHurtado\XJournal\Exceptions;

use RuntimeException;

class JournalEntryImmutableException extends RuntimeException
{
    public static function cannotUpdate(): self
    {
        return new self('Execution journal entries are append-only and cannot be updated.');
    }

    public static function cannotDelete(): self
    {
        return new self('Execution journal entries are append-only and cannot be deleted.');
    }
}
