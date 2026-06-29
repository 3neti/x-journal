<?php

namespace LBHurtado\XJournal\Contracts;

use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\JournalEventData;

interface JournalEventTransformerContract
{
    public function supports(JournalEventData $event): bool;

    public function transform(JournalEventData $event): ExecutionJournalEntryData;
}
