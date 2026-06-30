<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\SecondaryJournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class NullJournalSink implements SecondaryJournalSinkContract
{
    public function recordProjection(ExecutionJournalEntry $entry, ExecutionJournalEntryData $data): void
    {
        // Intentionally no-op.
    }
}
