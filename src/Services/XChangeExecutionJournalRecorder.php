<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\XChangeExecutionOutcomeData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class XChangeExecutionJournalRecorder
{
    public function __construct(
        protected JournalEventRecorder $events,
    ) {}

    public function record(XChangeExecutionOutcomeData $outcome): ExecutionJournalEntry
    {
        return $this->events->record($outcome->toJournalEvent());
    }
}
