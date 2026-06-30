<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Contracts\JournalVerificationServiceContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\JournalEventData;
use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalRetrievalQueryData;
use LBHurtado\XJournal\Data\JournalVisibilityProfileData;
use LBHurtado\XJournal\Exceptions\JournalEntryImmutableException;
use LBHurtado\XJournal\Exceptions\JournalEventTransformerNotFoundException;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\CockpitJournalReader;
use LBHurtado\XJournal\Services\DatabaseJournalSink;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use LBHurtado\XJournal\Services\JournalEventRecorder;
use LBHurtado\XJournal\Services\JournalEventTransformerRegistry;
use LBHurtado\XJournal\Services\JournalSinkDispatcher;
use LBHurtado\XJournal\Services\JournalVisibilityGate;
use LBHurtado\XJournal\Transformers\ExecutionResultJournalTransformer;

function architectureHardeningJournalEntryData(
    string $eventType = 'execution.result.recorded',
    string $actorId = 'system-1',
    string $actorType = 'system',
    string $subjectId = 'voucher-1',
    string $subjectType = 'voucher',
    string $executionId = 'exec-hardening-1',
): ExecutionJournalEntryData {
    return new ExecutionJournalEntryData(
        eventType: $eventType,
        occurredAt: CarbonImmutable::parse('2026-06-29T10:15:00Z'),
        actor: new ExecutionActorData(id: $actorId, type: $actorType, name: 'System'),
        subject: new ExecutionSubjectData(id: $subjectId, type: $subjectType, display: ucfirst($subjectType).' #1'),
        references: new ExecutionReferenceData(
            correlationId: 'corr-hardening-1',
            causationId: 'cause-hardening-1',
            executionId: $executionId,
        ),
        payload: ['status' => 'recorded'],
        metadata: ['source' => 'hardening-test'],
    );
}

function architectureHardeningJournalEvent(string $eventType): JournalEventData
{
    return new JournalEventData(
        eventType: $eventType,
        occurredAt: CarbonImmutable::parse('2026-06-29T10:15:00Z'),
        actor: new ExecutionActorData(id: 'system-1', type: 'system', name: 'System'),
        subject: new ExecutionSubjectData(id: 'voucher-1', type: 'voucher', display: 'Voucher #1'),
        references: new ExecutionReferenceData(executionId: 'exec-hardening-1'),
        payload: ['status' => 'recorded'],
    );
}

it('does not introduce runtime composer dependencies on settlement domain packages', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    $dependencies = array_keys(array_merge(
        $composer['require'] ?? [],
        $composer['require-dev'] ?? [],
    ));

    expect($dependencies)->not->toContain(
        '3neti/voucher',
        '3neti/x-change',
        '3neti/x-action',
        '3neti/x-feedback',
        '3neti/x-campaign',
        '3neti/settlement-envelope',
        '3neti/wallet',
        '3neti/cash',
    );
});

it('keeps core journal infrastructure bound as shared services', function () {
    expect(app(JournalSinkContract::class))->toBeInstanceOf(JournalSinkDispatcher::class)
        ->and(app(DatabaseJournalSink::class))->toBe(app(DatabaseJournalSink::class))
        ->and(app(JournalEntryRetriever::class))->toBe(app(JournalEntryRetriever::class))
        ->and(app(JournalVisibilityGate::class))->toBe(app(JournalVisibilityGate::class))
        ->and(app(JournalVerificationServiceContract::class))->toBe(app(JournalVerificationServiceContract::class))
        ->and(app(CockpitJournalReader::class))->toBe(app(CockpitJournalReader::class));
});

it('preserves append-only model guards as a hard architecture invariant', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(architectureHardeningJournalEntryData());

    expect(fn () => $entry->update(['event_type' => 'execution.changed']))
        ->toThrow(JournalEntryImmutableException::class, 'Execution journal entries are append-only and cannot be updated.')
        ->and(fn () => $entry->delete())
        ->toThrow(JournalEntryImmutableException::class, 'Execution journal entries are append-only and cannot be deleted.')
        ->and(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($entry->fresh()?->event_type)->toBe('execution.result.recorded');
});

it('keeps built-in event transformation explicit and fail-closed for unsupported domains', function () {
    $registry = app(JournalEventTransformerRegistry::class);

    expect($registry->transform(architectureHardeningJournalEvent('execution.result.recorded'))->metadata['transformer'])->toBe(ExecutionResultJournalTransformer::class)
        ->and($registry->transform(architectureHardeningJournalEvent('claim.lifecycle.submitted'))->metadata['domain'])->toBe('claim')
        ->and($registry->transform(architectureHardeningJournalEvent('provider.callback.received'))->metadata['domain'])->toBe('provider')
        ->and($registry->transform(architectureHardeningJournalEvent('reconciliation.comparison.recorded'))->metadata['domain'])->toBe('reconciliation')
        ->and($registry->transform(architectureHardeningJournalEvent('operator.action.recorded'))->metadata['domain'])->toBe('operator')
        ->and($registry->transform(architectureHardeningJournalEvent('campaign.distribution.planned'))->metadata['domain'])->toBe('campaign')
        ->and(fn () => $registry->transform(architectureHardeningJournalEvent('exception.unhandled')))
        ->toThrow(JournalEventTransformerNotFoundException::class);
});

it('keeps generic event recording fail-closed before persistence side effects', function () {
    expect(fn () => app(JournalEventRecorder::class)->record(architectureHardeningJournalEvent('exception.unhandled')))
        ->toThrow(JournalEventTransformerNotFoundException::class)
        ->and(ExecutionJournalEntry::query()->count())->toBe(0);
});

it('characterizes cockpit visibility filtering as post-retrieval windowing', function () {
    app(ExecutionJournalRecorder::class)->record(architectureHardeningJournalEntryData(
        actorId: 'operator-1',
        actorType: 'operator',
        subjectId: 'claim-1',
        subjectType: 'claim',
    ));
    app(ExecutionJournalRecorder::class)->record(architectureHardeningJournalEntryData(
        actorId: 'operator-2',
        actorType: 'operator',
        subjectId: 'claim-2',
        subjectType: 'claim',
    ));

    $view = app(CockpitJournalReader::class)->read(new LBHurtado\XJournal\Data\CockpitJournalQueryData(
        actor: JournalAccessActorData::fromArray([
            'id' => 'operator-1',
            'type' => 'operator',
        ]),
        query: new JournalRetrievalQueryData(limit: 2),
        visibilityProfile: JournalVisibilityProfileData::fromArray([]),
    ));

    expect($view->retrievedTotal)->toBe(2)
        ->and($view->visibleTotal)->toBe(1)
        ->and($view->entries->first()?->referenceNumber)->toBe('ERN-2026-000000001')
        ->and($view->hasMore)->toBeFalse();
});
