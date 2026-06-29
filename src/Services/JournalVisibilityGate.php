<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalVisibilityPolicyContract;
use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class JournalVisibilityGate
{
    /**
     * @param  array<int, JournalVisibilityPolicyContract>  $policies
     */
    public function __construct(
        protected array $policies = [],
    ) {}

    public function addPolicy(JournalVisibilityPolicyContract $policy): self
    {
        $this->policies[] = $policy;

        return $this;
    }

    public function decide(ExecutionJournalEntry $entry, JournalAccessActorData $actor): JournalAccessDecisionData
    {
        foreach ($this->policies as $policy) {
            $decision = $policy->decide($entry, $actor);

            if ($decision->allowed) {
                return $decision;
            }
        }

        return JournalAccessDecisionData::deny('no-policy-allowed-access');
    }

    public function allows(ExecutionJournalEntry $entry, JournalAccessActorData $actor): bool
    {
        return $this->decide($entry, $actor)->allowed;
    }
}
