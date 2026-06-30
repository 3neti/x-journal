<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalVisibilityAccessReasonLoggerContract;
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
        protected ?JournalVisibilityAccessReasonLoggerContract $accessReasonLogger = null,
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
                ($this->accessReasonLogger ?? new NullJournalVisibilityAccessReasonLogger())->log($entry, $actor, $decision);

                return $decision;
            }
        }

        $decision = JournalAccessDecisionData::deny('no-policy-allowed-access');
        ($this->accessReasonLogger ?? new NullJournalVisibilityAccessReasonLogger())->log($entry, $actor, $decision);

        return $decision;
    }

    public function allows(ExecutionJournalEntry $entry, JournalAccessActorData $actor): bool
    {
        return $this->decide($entry, $actor)->allowed;
    }
}
