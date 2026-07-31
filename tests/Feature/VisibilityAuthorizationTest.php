<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Contracts\JournalVisibilityAccessReasonLoggerContract;
use LBHurtado\XJournal\Contracts\JournalVisibilityPolicyContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Policies\ActorOrSubjectJournalVisibilityPolicy;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalVisibilityGate;

function visibleJournalEntry(): ExecutionJournalEntry
{
    return app(ExecutionJournalRecorder::class)->record(
        new ExecutionJournalEntryData(
            eventType: 'claim.lifecycle.submitted',
            occurredAt: CarbonImmutable::parse('2026-06-29T10:15:00Z'),
            actor: new ExecutionActorData(id: 'operator-1', type: 'operator', name: 'Ops User'),
            subject: new ExecutionSubjectData(id: 'claim-1', type: 'claim', display: 'Claim #1'),
            references: new ExecutionReferenceData(correlationId: 'corr-visible-1'),
            payload: ['claim_state' => 'submitted'],
        )
    );
}

it('normalizes access actors to a canonical shape', function () {
    $actor = JournalAccessActorData::fromArray([
        'id' => 123,
        'type' => 'operator',
        'roles' => ['admin', '', null],
        'permissions' => ['x-journal.view', 404],
        'metadata' => 'invalid',
    ]);

    expect($actor->toArray())->toBe([
        'id' => '123',
        'type' => 'operator',
        'roles' => ['admin'],
        'permissions' => ['x-journal.view', '404'],
        'metadata' => [],
    ]);
});

it('allows the journal actor to see its own journal entry', function () {
    $entry = visibleJournalEntry();

    $decision = app(JournalVisibilityGate::class)->decide($entry, JournalAccessActorData::fromArray([
        'id' => 'operator-1',
        'type' => 'operator',
    ]));

    expect($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBe('actor-match');
});

it('allows the journal subject to see its own journal entry', function () {
    $entry = visibleJournalEntry();

    $decision = app(JournalVisibilityGate::class)->decide($entry, JournalAccessActorData::fromArray([
        'id' => 'claim-1',
        'type' => 'claim',
    ]));

    expect($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBe('subject-match');
});

it('allows actors with explicit journal view permission', function () {
    $entry = visibleJournalEntry();

    $decision = app(JournalVisibilityGate::class)->decide($entry, JournalAccessActorData::fromArray([
        'id' => 'auditor-1',
        'type' => 'operator',
        'permissions' => ['x-journal.view'],
    ]));

    expect($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBe('permission:x-journal.view');
});

it('denies actors without a visible relationship', function () {
    $entry = visibleJournalEntry();

    $decision = app(JournalVisibilityGate::class)->decide($entry, JournalAccessActorData::fromArray([
        'id' => 'stranger-1',
        'type' => 'operator',
    ]));

    expect($decision->allowed)->toBeFalse()
        ->and($decision->reason)->toBe('no-policy-allowed-access');
});

it('does not alter journal truth while making visibility decisions', function () {
    $entry = visibleJournalEntry();
    $original = $entry->fresh()?->toArray();

    app(JournalVisibilityGate::class)->decide($entry, JournalAccessActorData::fromArray([
        'id' => 'stranger-1',
        'type' => 'operator',
    ]));

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($entry->fresh()?->toArray())->toBe($original);
});

it('allows package consumers to add visibility policies', function () {
    $entry = visibleJournalEntry();

    $gate = new JournalVisibilityGate;
    $gate->addPolicy(new class implements JournalVisibilityPolicyContract
    {
        public function decide(ExecutionJournalEntry $entry, JournalAccessActorData $actor): JournalAccessDecisionData
        {
            return $actor->can('custom.audit')
                ? JournalAccessDecisionData::allow('permission:custom.audit', self::class)
                : JournalAccessDecisionData::deny('missing-custom-audit', self::class);
        }
    });

    $decision = $gate->decide($entry, JournalAccessActorData::fromArray([
        'permissions' => ['custom.audit'],
    ]));

    expect($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBe('permission:custom.audit');
});

it('records visibility decisions through a configurable access reason logger', function () {
    $entry = visibleJournalEntry();
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
    $decision = $gate->decide($entry, JournalAccessActorData::fromArray([
        'id' => 'operator-1',
        'type' => 'operator',
    ]));

    expect($decision->allowed)->toBeTrue()
        ->and($calls)->toHaveCount(1)
        ->and($calls[0]['reason'])->toBe('actor-match');
});
