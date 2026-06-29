<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\JournalRetrievalQueryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalEntryRetriever;

function retrievalJournalEntryData(
    string $eventType = 'voucher.redeemed',
    int|string $actorId = 123,
    string $actorType = 'user',
    string $subjectId = 'voucher-1',
    string $subjectType = 'voucher',
    string $correlationId = 'corr-1',
    string $causationId = 'cause-1',
    string $executionId = 'exec-1',
): ExecutionJournalEntryData {
    return new ExecutionJournalEntryData(
        eventType: $eventType,
        occurredAt: CarbonImmutable::parse('2026-06-29 10:15:00', 'UTC'),
        actor: new ExecutionActorData(id: $actorId, type: $actorType, name: 'Beneficiary'),
        subject: new ExecutionSubjectData(id: $subjectId, type: $subjectType, display: 'Voucher'),
        references: new ExecutionReferenceData(
            correlationId: $correlationId,
            causationId: $causationId,
            executionId: $executionId,
            providerReference: 'provider-1',
        ),
        payload: ['status' => 'succeeded'],
        money: new ExecutionMoneyData(amount: '100.00', currency: 'PHP', minorAmount: 10000),
        metadata: ['source' => 'retrieval-test'],
    );
}

it('normalizes retrieval queries with bounded windows', function () {
    $query = JournalRetrievalQueryData::fromArray([
        'reference_number' => 'ERN-2026-000000001',
        'actor_id' => 123,
        'limit' => 999,
        'offset' => -10,
        'order' => 'DESC',
    ]);

    expect($query->toArray())->toMatchArray([
        'reference_number' => 'ERN-2026-000000001',
        'actor_id' => '123',
        'limit' => 200,
        'offset' => 0,
        'order' => 'desc',
    ]);
});

it('finds a journal entry by reference number', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(retrievalJournalEntryData());

    $found = app(JournalEntryRetriever::class)->findByReferenceNumber('ERN-2026-000000001');

    expect($found?->is($entry))->toBeTrue();
});

it('searches journal entries by indexed actor and subject projections', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $first = $recorder->record(retrievalJournalEntryData(actorId: 123, subjectId: 'voucher-1'));
    $recorder->record(retrievalJournalEntryData(actorId: 456, subjectId: 'voucher-2'));

    $result = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'actor_type' => 'user',
        'actor_id' => '123',
        'subject_type' => 'voucher',
        'subject_id' => 'voucher-1',
    ]));

    expect($result->total)->toBe(1)
        ->and($result->entries->first()?->is($first))->toBeTrue();
});

it('searches journal entries by correlation causation execution id and event type', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $recorder->record(retrievalJournalEntryData(eventType: 'voucher.generated', correlationId: 'corr-a', causationId: 'cause-a', executionId: 'exec-a'));
    $target = $recorder->record(retrievalJournalEntryData(eventType: 'voucher.redeemed', correlationId: 'corr-b', causationId: 'cause-b', executionId: 'exec-b'));

    $result = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'correlation_id' => 'corr-b',
        'causation_id' => 'cause-b',
        'execution_id' => 'exec-b',
        'event_type' => 'voucher.redeemed',
    ]));

    expect($result->total)->toBe(1)
        ->and($result->entries->first()?->is($target))->toBeTrue();
});

it('supports deterministic pagination windows', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $first = $recorder->record(retrievalJournalEntryData(subjectId: 'voucher-1'));
    $second = $recorder->record(retrievalJournalEntryData(subjectId: 'voucher-2'));
    $third = $recorder->record(retrievalJournalEntryData(subjectId: 'voucher-3'));

    $window = app(JournalEntryRetriever::class)->search(new JournalRetrievalQueryData(
        limit: 1,
        offset: 1,
    ));

    expect($first->reference_number)->toBe('ERN-2026-000000001')
        ->and($window->entries)->toHaveCount(1)
        ->and($window->entries->first()?->is($second))->toBeTrue()
        ->and($third->reference_number)->toBe('ERN-2026-000000003')
        ->and($window->total)->toBe(3)
        ->and($window->hasMore())->toBeTrue();
});

it('supports descending retrieval order', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $first = $recorder->record(retrievalJournalEntryData(subjectId: 'voucher-1'));
    $second = $recorder->record(retrievalJournalEntryData(subjectId: 'voucher-2'));

    $result = app(JournalEntryRetriever::class)->search(new JournalRetrievalQueryData(order: 'desc'));

    expect($result->entries->pluck('reference_number')->all())->toBe([
        $second->reference_number,
        $first->reference_number,
    ]);
});

it('does not mutate journal entries while searching', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(retrievalJournalEntryData());
    $original = $entry->fresh()?->toArray();

    app(JournalEntryRetriever::class)->search(new JournalRetrievalQueryData(actorId: '123'));

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($entry->fresh()?->toArray())->toBe($original);
});
