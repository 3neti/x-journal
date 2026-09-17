<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use LBHurtado\XJournal\Services\JournalIntegrityVerifier;

function sourcedJournalEntry(string $sourceEventId, string $eventType = 'funding.synced'): ExecutionJournalEntryData
{
    return new ExecutionJournalEntryData(
        eventType: $eventType,
        occurredAt: CarbonImmutable::parse('2026-09-17 01:00:00', 'UTC'),
        actor: new ExecutionActorData(id: 'system', type: 'system', name: 'X-Change'),
        subject: new ExecutionSubjectData(id: 'address-1', type: 'funding_address'),
        references: new ExecutionReferenceData(correlationId: 'corr-'.$sourceEventId),
        idempotencyKey: 'x-change:audit:'.$sourceEventId,
        payload: ['status' => 'recorded'],
        metadata: ['source' => 'x-change-audit'],
        sourceSystem: 'x-change',
        sourceEventId: $sourceEventId,
    );
}

it('persists and retrieves indexed source event identities', function (): void {
    $entry = app(ExecutionJournalRecorder::class)->record(sourcedJournalEntry('evt-001'));
    $found = app(JournalEntryRetriever::class)->findBySourceIdentity('x-change', 'evt-001');

    expect($entry->source_system)->toBe('x-change')
        ->and($entry->source_event_id)->toBe('evt-001')
        ->and($found?->is($entry))->toBeTrue()
        ->and(app(JournalIntegrityVerifier::class)->verify()->verified)->toBeTrue();
});

it('returns stable newest-first source pages without offsets', function (): void {
    $recorder = app(ExecutionJournalRecorder::class);

    foreach (range(1, 5) as $number) {
        $recorder->record(sourcedJournalEntry('evt-'.$number, $number % 2 === 0 ? 'funding.synced' : 'voucher.created'));
    }

    $first = app(JournalEntryRetriever::class)->sourcePage('x-change', 2);
    $second = app(JournalEntryRetriever::class)->sourcePage('x-change', 2, $first['next_cursor']);
    $filtered = app(JournalEntryRetriever::class)->sourcePage('x-change', 10, eventType: 'funding.synced');

    expect($first['entries']->pluck('source_event_id')->all())->toBe(['evt-5', 'evt-4'])
        ->and($first['has_more'])->toBeTrue()
        ->and($second['entries']->pluck('source_event_id')->all())->toBe(['evt-3', 'evt-2'])
        ->and($filtered['entries']->pluck('source_event_id')->all())->toBe(['evt-4', 'evt-2']);
});

it('keeps the journal head synchronized with the canonical chain', function (): void {
    $recorder = app(ExecutionJournalRecorder::class);
    $first = $recorder->record(sourcedJournalEntry('evt-head-1'));
    $second = $recorder->record(sourcedJournalEntry('evt-head-2'));

    expect($second->integrity['previous_hash'])->toBe($first->integrity['hash'])
        ->and(DB::table('execution_journal_heads')->where('id', 1)->value('current_hash'))
        ->toBe($second->integrity['hash'])
        ->and(app(JournalIntegrityVerifier::class)->verify()->verified)->toBeTrue()
        ->and(ExecutionJournalEntry::query()->count())->toBe(2);
});
