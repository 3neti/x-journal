<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalIdempotencyKeyResolverContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;

class DefaultJournalIdempotencyKeyResolver implements JournalIdempotencyKeyResolverContract
{
    public function resolve(?string $idempotencyKey, ExecutionJournalEntryData $entry): ?string
    {
        return $idempotencyKey;
    }
}
