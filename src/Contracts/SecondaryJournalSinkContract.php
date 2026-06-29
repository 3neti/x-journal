<?php

namespace LBHurtado\XJournal\Contracts;

use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface SecondaryJournalSinkContract
{
    public function recordProjection(ExecutionJournalEntry $entry, ExecutionJournalEntryData $data): void;
}
