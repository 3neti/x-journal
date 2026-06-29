<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\ReconciliationEventData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class ReconciliationJournalRecorder
{
    public function __construct(
        protected JournalEventRecorder $events,
    ) {}

    public function record(ReconciliationEventData $event): ExecutionJournalEntry
    {
        return $this->events->record($event->toJournalEvent());
    }
}
