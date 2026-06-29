<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\CampaignEventData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class CampaignJournalRecorder
{
    public function __construct(
        protected JournalEventRecorder $events,
    ) {}

    public function record(CampaignEventData $event): ExecutionJournalEntry
    {
        return $this->events->record($event->toJournalEvent());
    }
}
