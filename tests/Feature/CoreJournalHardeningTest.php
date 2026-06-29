<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Models\ExecutionJournalReferenceCounter;
use LBHurtado\XJournal\Services\ExecutionJournalIntegrityHasher;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\ExecutionReferenceNumberGenerator;

function hardeningJournalEntryData(?string $referenceNumber = null): ExecutionJournalEntryData
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

it('uses a durable counter table for ERN sequencing by prefix and year', function () {
    $generator = app(ExecutionReferenceNumberGenerator::class);

    expect($generator->generate(CarbonImmutable::parse('2026-06-29')))->toBe('ERN-2026-000000001')
        ->and($generator->generate(CarbonImmutable::parse('2026-07-01')))->toBe('ERN-2026-000000002')
        ->and($generator->generate(CarbonImmutable::parse('2027-01-01')))->toBe('ERN-2027-000000001');

    expect(ExecutionJournalReferenceCounter::query()->where('prefix', 'ERN')->where('year', '2026')->value('next_sequence'))->toBe(3)
        ->and(ExecutionJournalReferenceCounter::query()->where('prefix', 'ERN')->where('year', '2027')->value('next_sequence'))->toBe(2);
});

it('supports configurable ERN prefixes without sharing counters', function () {
    config()->set('x-journal.reference_number.prefix', 'JRN');

    $generator = app(ExecutionReferenceNumberGenerator::class);

    expect($generator->generate(CarbonImmutable::parse('2026-06-29')))->toBe('JRN-2026-000000001');

    config()->set('x-journal.reference_number.prefix', 'ERN');

    expect($generator->generate(CarbonImmutable::parse('2026-06-29')))->toBe('ERN-2026-000000001');
});

it('stores indexed scalar projections for common journal queries', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(hardeningJournalEntryData());

    expect($entry->actor_type)->toBe('user')
        ->and($entry->actor_id)->toBe('123')
        ->and($entry->subject_type)->toBe('voucher')
        ->and($entry->subject_id)->toBe('voucher-1')
        ->and($entry->correlation_id)->toBe('corr-1')
        ->and($entry->causation_id)->toBe('cause-1')
        ->and($entry->execution_id)->toBe('exec-1')
        ->and(ExecutionJournalEntry::query()->where('execution_id', 'exec-1')->first()?->is($entry))->toBeTrue();
});

it('calculates deterministic integrity hashes and chains to the previous hash', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $first = $recorder->record(hardeningJournalEntryData());
    $second = $recorder->record(hardeningJournalEntryData());

    expect($first->integrity['hash'])->toBeString()
        ->and($first->integrity['previous_hash'])->toBeNull()
        ->and($second->integrity['previous_hash'])->toBe($first->integrity['hash'])
        ->and($second->integrity['hash'])->not->toBe($first->integrity['hash']);
});

it('exposes deterministic integrity hashing for verification workflows', function () {
    $entry = hardeningJournalEntryData('ERN-2026-000000001');
    $integrity = ['previous_hash' => null];

    $first = app(ExecutionJournalIntegrityHasher::class)->hash($entry, $integrity);
    $second = app(ExecutionJournalIntegrityHasher::class)->hash($entry, $integrity);

    expect($first)->toBe($second)
        ->and($first)->toHaveLength(64);
});

it('allows package consumers to replace the sink through the journal sink contract', function () {
    $sink = new class implements JournalSinkContract
    {
        public ?ExecutionJournalEntryData $recorded = null;

        public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
        {
            $this->recorded = $entry;

            return new ExecutionJournalEntry([
                'reference_number' => $entry->referenceNumber,
                'event_type' => $entry->eventType,
            ]);
        }
    };

    app()->instance(JournalSinkContract::class, $sink);
    app()->forgetInstance(ExecutionJournalRecorder::class);

    $entry = app(ExecutionJournalRecorder::class)->record(hardeningJournalEntryData());

    expect($sink->recorded)->toBeInstanceOf(ExecutionJournalEntryData::class)
        ->and($sink->recorded?->referenceNumber)->toBe('ERN-2026-000000001')
        ->and($entry->exists)->toBeFalse();
});
