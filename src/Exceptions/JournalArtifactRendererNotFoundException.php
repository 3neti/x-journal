<?php

namespace LBHurtado\XJournal\Exceptions;

use RuntimeException;

class JournalArtifactRendererNotFoundException extends RuntimeException
{
    public static function forProfile(string $type, string $format): self
    {
        return new self("No journal artifact renderer is registered for [{$type}:{$format}].");
    }
}
