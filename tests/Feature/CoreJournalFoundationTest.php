<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Exceptions\JournalEntryImmutableException;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\DatabaseJournalSink;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

function journalEntryData(?string $referenceNumber = null): ExecutionJournalEntryData
{
    return new ExecutionJournalEntryData(
        eventType: 'voucher.redeemed',
        occurredAt: CarbonImmutable::parse('2026-06-29 10:15:00', 'UTC'),
        actor: new ExecutionActorData(id: 123, type: 'user', name: 'Beneficiary'),
        subject: new ExecutionSubjectData(id: 'voucher-1', type: 'voucher', display: 'Voucher #1'),
        references: new ExecutionReferenceData(
            correlationId: 'corr-1',
            causationId: 'cause-1',
            executionId: 'exec-1',
            providerReference: 'provider-1',
        ),
        payload: ['status' => 'succeeded'],
        money: new ExecutionMoneyData(amount: '100.00', currency: 'PHP', minorAmount: 10000),
        metadata: ['source' => 'test'],
        referenceNumber: $referenceNumber,
    );
}

it('records execution journal entries through the recorder with a generated ERN', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(journalEntryData());

    expect($entry)->toBeInstanceOf(ExecutionJournalEntry::class)
        ->and($entry->reference_number)->toBe('ERN-2026-000000001')
        ->and($entry->event_type)->toBe('voucher.redeemed')
        ->and($entry->occurred_at->toJSON())->toBe('2026-06-29T10:15:00.000000Z')
        ->and($entry->actor)->toBe([
            'id' => '123',
            'type' => 'user',
            'name' => 'Beneficiary',
            'metadata' => [],
        ])
        ->and($entry->subject)->toBe([
            'id' => 'voucher-1',
            'type' => 'voucher',
            'display' => 'Voucher #1',
            'metadata' => [],
        ])
        ->and($entry->money)->toBe([
            'amount' => '100.00',
            'currency' => 'PHP',
            'minor_amount' => 10000,
            'metadata' => [],
        ])
        ->and($entry->references['execution_id'])->toBe('exec-1')
        ->and($entry->payload)->toBe(['status' => 'succeeded'])
        ->and($entry->metadata)->toBe(['source' => 'test']);
});

it('increments ERNs within the occurred year', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $first = $recorder->record(journalEntryData());
    $second = $recorder->record(journalEntryData());

    expect($first->reference_number)->toBe('ERN-2026-000000001')
        ->and($second->reference_number)->toBe('ERN-2026-000000002');
});

it('persists entries through the database sink when a reference number is already assigned', function () {
    $entry = app(DatabaseJournalSink::class)->record(
        journalEntryData('ERN-2026-000000777')
    );

    expect($entry->reference_number)->toBe('ERN-2026-000000777')
        ->and(ExecutionJournalEntry::query()->count())->toBe(1);
});

it('prevents updates because journal entries are append-only', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(journalEntryData());

    expect(fn () => $entry->update(['event_type' => 'voucher.changed']))
        ->toThrow(JournalEntryImmutableException::class);
});

it('prevents deletes because journal entries are append-only', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(journalEntryData());

    expect(fn () => $entry->delete())
        ->toThrow(JournalEntryImmutableException::class);
});
