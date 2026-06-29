<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\JournalEventData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class JournalEventRecorder
{
    public function __construct(
        protected JournalEventTransformerRegistry $transformers,
        protected ExecutionJournalRecorder $recorder,
    ) {}

    public function record(JournalEventData $event): ExecutionJournalEntry
    {
        return $this->recorder->record(
            $this->transformers->transform($event)
        );
    }
}
