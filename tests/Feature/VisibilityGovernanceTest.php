<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Contracts\JournalVisibilityAccessReasonLoggerContract;
use LBHurtado\XJournal\Contracts\JournalVisibilityProfileResolverContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Data\JournalVisibilityProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Policies\ActorOrSubjectJournalVisibilityPolicy;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalVisibilityGate;

afterEach(function () {
    config()->set('x-journal.visibility.role_profiles', []);
    config()->set('x-journal.visibility.event_profiles', []);
});

function governedJournalEntry(): ExecutionJournalEntry
{
    return app(ExecutionJournalRecorder::class)->record(
        new ExecutionJournalEntryData(
            eventType: 'claim.lifecycle.submitted',
            occurredAt: CarbonImmutable::parse('2026-06-29T10:15:00Z'),
            actor: new ExecutionActorData(id: 'operator-1', type: 'operator', name: 'Ops User'),
            subject: new ExecutionSubjectData(id: 'claim-1', type: 'claim', display: 'Claim #1'),
            references: new ExecutionReferenceData(correlationId: 'corr-governed-1'),
            payload: ['claim_state' => 'submitted', 'status' => 'ok'],
            metadata: ['source' => 'governance-test'],
        )
    );
}

it('resolves role-based visibility profiles from configuration', function () {
    $entry = governedJournalEntry();

    config()->set('x-journal.visibility.role_profiles', [
        'finance' => [
            'name' => JournalVisibilityProfileData::PROFILE_REDACTED,
            'redact_payload_keys' => ['claim_state'],
        ],
        'support' => JournalVisibilityProfileData::PROFILE_SUMMARY,
    ]);

    $resolver = app(JournalVisibilityProfileResolverContract::class);
    $requested = JournalVisibilityProfileData::fromArray([]);

    $financeProfile = $resolver->resolve(
        $entry,
        JournalAccessActorData::fromArray(['id' => 'auditor-1', 'type' => 'operator', 'roles' => ['finance']]),
        JournalAccessDecisionData::allow('allow', 'test.policy'),
        $requested,
    );

    $supportProfile = $resolver->resolve(
        $entry,
        JournalAccessActorData::fromArray(['id' => 'auditor-2', 'type' => 'operator', 'roles' => ['support']]),
        JournalAccessDecisionData::allow('allow', 'test.policy'),
        $requested,
    );

    expect($financeProfile->name)->toBe(JournalVisibilityProfileData::PROFILE_REDACTED)
        ->and($financeProfile->redactPayloadKeys)->toBe(['claim_state'])
        ->and($supportProfile->name)->toBe(JournalVisibilityProfileData::PROFILE_SUMMARY);
});

it('supports event visibility profiles and role-specific overrides in governance matrices', function () {
    $entry = governedJournalEntry();

    config()->set('x-journal.visibility.role_profiles', [
        'finance' => JournalVisibilityProfileData::PROFILE_SUMMARY,
    ]);

    config()->set('x-journal.visibility.event_profiles', [
        'claim.lifecycle.submitted' => [
            'roles' => [
                'compliance' => 'raw',
            ],
            'default' => 'redacted',
        ],
        'provider.callback.received' => 'summary',
    ]);

    $resolver = app(JournalVisibilityProfileResolverContract::class);
    $requested = JournalVisibilityProfileData::fromArray([]);

    $finance = $resolver->resolve(
        $entry,
        JournalAccessActorData::fromArray(['roles' => ['finance']]),
        JournalAccessDecisionData::allow('allow', 'test.policy'),
        $requested,
    );

    $compliance = $resolver->resolve(
        $entry,
        JournalAccessActorData::fromArray(['roles' => ['compliance']]),
        JournalAccessDecisionData::allow('allow', 'test.policy'),
        $requested,
    );

    expect($finance->name)->toBe(JournalVisibilityProfileData::PROFILE_REDACTED)
        ->and($compliance->name)->toBe(JournalVisibilityProfileData::PROFILE_RAW)
        ->and($resolver->resolve(
            $entry,
            JournalAccessActorData::fromArray(['roles' => ['support']]),
            JournalAccessDecisionData::allow('allow', 'test.policy'),
            $requested,
        )->name)->toBe(JournalVisibilityProfileData::PROFILE_REDACTED);
});

it('records denied visibility decisions through the configured access reason logger', function () {
    $entry = governedJournalEntry();
    $calls = [];

    $logger = new class($calls) implements JournalVisibilityAccessReasonLoggerContract
    {
        public function __construct(public array &$calls) {}

        public function log(ExecutionJournalEntry $entry, JournalAccessActorData $actor, JournalAccessDecisionData $decision): void
        {
            $this->calls[] = [
                'entry_reference_number' => $entry->reference_number,
                'actor_id' => $actor->id,
                'allowed' => $decision->allowed,
                'reason' => $decision->reason,
                'policy' => $decision->policy,
            ];
        }
    };

    $gate = new JournalVisibilityGate([], $logger);
    $gate->addPolicy(new ActorOrSubjectJournalVisibilityPolicy);

    $decision = $gate->decide($entry, JournalAccessActorData::fromArray(['id' => 'stranger-1', 'type' => 'operator']));

    expect($decision->allowed)->toBeFalse()
        ->and($calls)->toHaveCount(1)
        ->and($calls[0]['allowed'])->toBeFalse()
        ->and($calls[0]['reason'])->toBe('no-policy-allowed-access');
});
