<?php

namespace LBHurtado\XJournal\Contracts;

use LBHurtado\XJournal\Data\ExecutionJournalEntryData;

interface JournalIdempotencyKeyResolverContract
{
    public function resolve(?string $idempotencyKey, ExecutionJournalEntryData $entry): ?string;
}
