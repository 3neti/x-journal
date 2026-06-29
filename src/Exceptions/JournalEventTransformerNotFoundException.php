<?php

namespace LBHurtado\XJournal\Exceptions;

use RuntimeException;

class JournalEventTransformerNotFoundException extends RuntimeException
{
    public static function forEventType(string $eventType): self
    {
        return new self("No journal event transformer is registered for [{$eventType}].");
    }
}
