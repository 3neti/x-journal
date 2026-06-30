<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Data\CockpitJournalQueryData;
use LBHurtado\XJournal\Data\JournalVisibilityProfileData;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\OperatorActionData;
use LBHurtado\XJournal\Contracts\JournalCockpitEntryPresentationContract;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\CockpitJournalReader;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use LBHurtado\XJournal\Services\JournalVisibilityGate;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\OperatorActionJournalRecorder;

function cockpitJournalEntry(
    string $eventType = 'execution.result.recorded',
    string $actorId = 'system-1',
    string $actorType = 'system',
    string $subjectId = 'voucher-1',
    string $subjectType = 'voucher',
    string $executionId = 'exec-1',
    string $correlationId = 'corr-1',
    string $causationId = 'cause-1',
): ExecutionJournalEntry {
    return app(ExecutionJournalRecorder::class)->record(
        new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse('2026-06-29T10:15:00Z'),
            actor: new ExecutionActorData(id: $actorId, type: $actorType, name: 'System'),
            subject: new ExecutionSubjectData(id: $subjectId, type: $subjectType, display: ucfirst($subjectType).' #1'),
            references: new ExecutionReferenceData(
                correlationId: $correlationId,
                causationId: $causationId,
                executionId: $executionId,
                providerReference: 'provider-ref-1',
                externalReference: 'external-ref-1',
                metadata: [
                    'claim_id' => 'claim-1',
                    'campaign_id' => 'campaign-1',
                ],
            ),
            payload: [
                'status' => 'recorded',
            ],
            metadata: [
                'source' => 'cockpit-test',
            ],
        )
    );
}

it('normalizes cockpit journal queries for operator views', function () {
    $query = CockpitJournalQueryData::fromArray([
        'actor' => [
            'id' => 123,
            'type' => 'operator',
            'roles' => ['ops', null, ''],
            'permissions' => ['x-journal.view'],
        ],
        'query' => [
            'execution_id' => 'exec-1',
            'limit' => 999,
            'offset' => -5,
            'order' => 'DESC',
        ],
        'context' => [
            'surface' => 'cockpit.timeline',
        ],
        'metadata' => [
            'request_id' => 'cockpit-request-1',
        ],
    ]);

    expect($query->toArray())->toMatchArray([
        'actor' => [
            'id' => '123',
            'type' => 'operator',
            'roles' => ['ops'],
            'permissions' => ['x-journal.view'],
            'metadata' => [],
        ],
        'query' => [
            'reference_number' => null,
            'actor_type' => null,
            'actor_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'correlation_id' => null,
            'causation_id' => null,
            'execution_id' => 'exec-1',
            'event_type' => null,
            'limit' => 200,
            'offset' => 0,
            'order' => 'desc',
        ],
        'context' => [
            'surface' => 'cockpit.timeline',
        ],
        'metadata' => [
            'source' => 'cockpit',
            'integration' => 'cockpit.journal',
            'request_id' => 'cockpit-request-1',
        ],
        'visibility_profile' => [
            'name' => 'raw',
            'include_actor' => true,
            'include_subject' => true,
            'include_references' => true,
            'include_payload' => true,
            'include_metadata' => true,
            'redact_actor_keys' => [],
            'redact_subject_keys' => [],
            'redact_payload_keys' => [],
            'redact_metadata_keys' => [],
        ],
    ]);
});

it('returns cockpit read models for actors with explicit journal visibility', function () {
    cockpitJournalEntry(eventType: 'execution.result.recorded', executionId: 'exec-1');
    cockpitJournalEntry(eventType: 'provider.callback.received', executionId: 'exec-1', causationId: 'provider-ref-1');
    cockpitJournalEntry(eventType: 'campaign.distribution.planned', subjectId: 'campaign-1', subjectType: 'campaign', executionId: 'exec-1', causationId: 'program-approval-1');

    $view = app(CockpitJournalReader::class)->read(CockpitJournalQueryData::fromArray([
        'actor' => [
            'id' => 'operator-1',
            'type' => 'operator',
            'permissions' => ['x-journal.view'],
        ],
        'query' => [
            'execution_id' => 'exec-1',
            'order' => 'asc',
        ],
        'context' => [
            'surface' => 'cockpit.timeline',
        ],
    ]));

    expect($view->retrievedTotal)->toBe(3)
        ->and($view->visibleTotal)->toBe(3)
        ->and($view->entries->pluck('eventType')->all())->toBe([
            'execution.result.recorded',
            'provider.callback.received',
            'campaign.distribution.planned',
        ])
        ->and($view->entries->first()?->visibilityReason)->toBe('permission:x-journal.view')
        ->and($view->toArray()['entries'][0])->toMatchArray([
            'event_type' => 'execution.result.recorded',
            'references' => [
                'correlation_id' => 'corr-1',
                'causation_id' => 'cause-1',
                'execution_id' => 'exec-1',
                'provider_reference' => 'provider-ref-1',
                'external_reference' => 'external-ref-1',
                'metadata' => [
                    'claim_id' => 'claim-1',
                    'campaign_id' => 'campaign-1',
                ],
            ],
            'payload' => [
                'status' => 'recorded',
            ],
        ]);
});

it('does not bypass journal visibility for cockpit reads', function () {
    cockpitJournalEntry(actorId: 'operator-1', actorType: 'operator', subjectId: 'claim-1', subjectType: 'claim');

    $view = app(CockpitJournalReader::class)->read(CockpitJournalQueryData::fromArray([
        'actor' => [
            'id' => 'stranger-1',
            'type' => 'operator',
        ],
        'query' => [
            'execution_id' => 'exec-1',
        ],
    ]));

    expect($view->retrievedTotal)->toBe(1)
        ->and($view->visibleTotal)->toBe(0)
        ->and($view->entries)->toHaveCount(0);
});

it('allows cockpit reads through subject visibility without global journal permission', function () {
    cockpitJournalEntry(subjectId: 'claim-1', subjectType: 'claim');

    $view = app(CockpitJournalReader::class)->read(CockpitJournalQueryData::fromArray([
        'actor' => [
            'id' => 'claim-1',
            'type' => 'claim',
        ],
        'query' => [
            'subject_type' => 'claim',
            'subject_id' => 'claim-1',
        ],
    ]));

    expect($view->visibleTotal)->toBe(1)
        ->and($view->entries->first()?->visibilityReason)->toBe('subject-match');
});

it('does not execute operator actions or mutate journal entries while reading for cockpit', function () {
    $entry = app(OperatorActionJournalRecorder::class)->record(OperatorActionData::fromArray([
        'event_type' => 'operator.action.requested',
        'actor' => [
            'id' => 'operator-1',
            'type' => 'operator',
            'name' => 'Ops User',
        ],
        'subject' => [
            'id' => 'claim-1',
            'type' => 'claim',
            'display' => 'Claim #1',
        ],
        'references' => [
            'correlation_id' => 'operator-corr-1',
            'causation_id' => 'manual-review-1',
            'execution_id' => 'exec-1',
        ],
        'action' => [
            'key' => 'approve_manual_review',
            'target_type' => 'claim',
            'target_id' => 'claim-1',
        ],
        'payload' => [
            'requested_transition' => 'approved',
        ],
    ]));
    $before = $entry->fresh()?->toArray();

    $view = app(CockpitJournalReader::class)->read(CockpitJournalQueryData::fromArray([
        'actor' => [
            'id' => 'operator-1',
            'type' => 'operator',
            'permissions' => ['x-journal.view'],
        ],
        'query' => [
            'event_type' => 'operator.action.requested',
        ],
    ]));

    expect($view->visibleTotal)->toBe(1)
        ->and($view->entries->first()?->payload)->toMatchArray([
            'requested_transition' => 'approved',
        ])
        ->and($view->entries->first()?->metadata)->not->toHaveKey('workflow_mutated')
        ->and($view->entries->first()?->metadata)->not->toHaveKey('execution_performed')
        ->and($view->entries->first()?->metadata)->not->toHaveKey('money_moved')
        ->and(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($entry->fresh()?->toArray())->toBe($before);
});

it('supports visibility profiles and redacts cockpit entry payloads for summary views', function () {
    cockpitJournalEntry(
        eventType: 'execution.result.recorded',
        actorId: 'operator-1',
        actorType: 'system',
        subjectId: 'voucher-1',
        subjectType: 'voucher',
        executionId: 'exec-summary-1',
    );

    $view = app(CockpitJournalReader::class)->read(CockpitJournalQueryData::fromArray([
        'actor' => [
            'id' => 'operator-1',
            'type' => 'system',
            'permissions' => ['x-journal.view'],
        ],
        'query' => [
            'execution_id' => 'exec-summary-1',
        ],
        'visibility_profile' => [
            'name' => 'summary',
        ],
    ]));

    expect($view->visibleTotal)->toBe(1)
        ->and($view->entries->first()?->actor)->toBe([
            'id' => 'operator-1',
            'type' => 'system',
            'metadata' => [],
        ])
        ->and($view->entries->first()?->subject)->toBe([
            'id' => 'voucher-1',
            'type' => 'voucher',
            'metadata' => [],
        ])
        ->and($view->entries->first()?->payload)->toBe([])
        ->and($view->entries->first()?->metadata)->toBe([]);
});

it('supports redacted visibility profiles with configurable field suppression', function () {
    cockpitJournalEntry(
        eventType: 'execution.result.recorded',
        actorId: 'operator-1',
        actorType: 'system',
        subjectId: 'voucher-2',
        subjectType: 'voucher',
        executionId: 'exec-redacted-1',
    );

    $view = app(CockpitJournalReader::class)->read(CockpitJournalQueryData::fromArray([
        'actor' => [
            'id' => 'operator-1',
            'type' => 'system',
            'permissions' => ['x-journal.view'],
        ],
        'query' => [
            'execution_id' => 'exec-redacted-1',
        ],
        'visibility_profile' => [
            'name' => 'redacted',
            'redact_payload_keys' => ['status'],
            'redact_metadata_keys' => ['source'],
        ],
    ]));

    expect($view->visibleTotal)->toBe(1)
        ->and($view->entries->first()?->payload)->toBe([])
        ->and($view->entries->first()?->metadata)->toBe([]);
});

it('allows package consumers to override cockpit visibility presentation contract', function () {
    cockpitJournalEntry(
        eventType: 'execution.result.recorded',
        executionId: 'exec-contract-1',
    );

    $reader = new CockpitJournalReader(
        app(JournalEntryRetriever::class),
        app(JournalVisibilityGate::class),
        new class implements JournalCockpitEntryPresentationContract
        {
            public function present(
                \LBHurtado\XJournal\Models\ExecutionJournalEntry $entry,
                \LBHurtado\XJournal\Data\JournalAccessActorData $actor,
                \LBHurtado\XJournal\Data\JournalAccessDecisionData $decision,
                JournalVisibilityProfileData $profile,
            ): \LBHurtado\XJournal\Data\CockpitJournalEntryData {
                return \LBHurtado\XJournal\Data\CockpitJournalEntryData::fromEntryWithProfile(
                    $entry,
                    $decision,
                    JournalVisibilityProfileData::fromArray(['name' => 'summary'])
                );
            }
        }
    );

    $view = $reader->read(CockpitJournalQueryData::fromArray([
        'actor' => [
            'id' => 'operator-1',
            'type' => 'system',
            'permissions' => ['x-journal.view'],
        ],
        'query' => [
            'execution_id' => 'exec-contract-1',
        ],
    ]));

    expect($view->entries->first()?->payload)->toBe([]);
});
