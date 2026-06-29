<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\ProviderCallbackData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class ProviderCallbackJournalRecorder
{
    public function __construct(
        protected JournalEventRecorder $events,
    ) {}

    public function record(ProviderCallbackData $callback): ExecutionJournalEntry
    {
        return $this->events->record($callback->toJournalEvent());
    }
}
