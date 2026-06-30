<?php

namespace LBHurtado\XJournal\Contracts;

use LBHurtado\XJournal\Data\CockpitJournalEntryData;
use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Data\JournalVisibilityProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface JournalCockpitEntryPresentationContract
{
    public function present(
        ExecutionJournalEntry $entry,
        JournalAccessActorData $actor,
        JournalAccessDecisionData $decision,
        JournalVisibilityProfileData $profile,
    ): CockpitJournalEntryData;
}
