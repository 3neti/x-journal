<?php

namespace LBHurtado\XJournal\Policies;

use LBHurtado\XJournal\Contracts\JournalVisibilityPolicyContract;
use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class ActorOrSubjectJournalVisibilityPolicy implements JournalVisibilityPolicyContract
{
    public function decide(ExecutionJournalEntry $entry, JournalAccessActorData $actor): JournalAccessDecisionData
    {
        if ($actor->can('x-journal.view')) {
            return JournalAccessDecisionData::allow('permission:x-journal.view', static::class);
        }

        if ($actor->id !== null && $actor->type !== null) {
            if ($actor->id === $entry->actor_id && $actor->type === $entry->actor_type) {
                return JournalAccessDecisionData::allow('actor-match', static::class);
            }

            if ($actor->id === $entry->subject_id && $actor->type === $entry->subject_type) {
                return JournalAccessDecisionData::allow('subject-match', static::class);
            }
        }

        return JournalAccessDecisionData::deny('no-visible-relationship', static::class);
    }
}
