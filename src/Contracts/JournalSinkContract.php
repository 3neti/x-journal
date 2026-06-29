<?php

namespace LBHurtado\XJournal\Contracts;

use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface JournalSinkContract
{
    public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry;
}
