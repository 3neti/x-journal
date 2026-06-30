<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalCockpitEntryPresentationContract;
use LBHurtado\XJournal\Data\CockpitJournalEntryData;
use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Data\JournalVisibilityProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class CockpitJournalEntryPresenter implements JournalCockpitEntryPresentationContract
{
    public function present(
        ExecutionJournalEntry $entry,
        JournalAccessActorData $actor,
        JournalAccessDecisionData $decision,
        JournalVisibilityProfileData $profile,
    ): CockpitJournalEntryData {
        return CockpitJournalEntryData::fromEntryWithProfile($entry, $decision, $profile);
    }
}
