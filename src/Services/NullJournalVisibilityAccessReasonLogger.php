<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalVisibilityAccessReasonLoggerContract;
use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class NullJournalVisibilityAccessReasonLogger implements JournalVisibilityAccessReasonLoggerContract
{
    public function log(ExecutionJournalEntry $entry, JournalAccessActorData $actor, JournalAccessDecisionData $decision): void
    {
        // No-op logger used as a safe default when packages do not wire auditing yet.
    }
}
