<?php

namespace LBHurtado\XJournal\Contracts;

use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface JournalVisibilityPolicyContract
{
    public function decide(ExecutionJournalEntry $entry, JournalAccessActorData $actor): JournalAccessDecisionData;
}
