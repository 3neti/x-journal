<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\OperatorActionData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class OperatorActionJournalRecorder
{
    public function __construct(
        protected JournalEventRecorder $events,
    ) {}

    public function record(OperatorActionData $event): ExecutionJournalEntry
    {
        return $this->events->record($event->toJournalEvent());
    }
}
