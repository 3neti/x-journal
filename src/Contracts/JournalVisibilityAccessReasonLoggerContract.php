<?php

namespace LBHurtado\XJournal\Contracts;

use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface JournalVisibilityAccessReasonLoggerContract
{
    public function log(ExecutionJournalEntry $entry, JournalAccessActorData $actor, JournalAccessDecisionData $decision): void;
}
