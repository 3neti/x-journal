<?php

namespace LBHurtado\XJournal\Contracts;

use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Data\JournalVisibilityProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface JournalVisibilityProfileResolverContract
{
    public function resolve(
        ExecutionJournalEntry $entry,
        JournalAccessActorData $actor,
        JournalAccessDecisionData $decision,
        JournalVisibilityProfileData $requestedProfile,
    ): JournalVisibilityProfileData;
}
