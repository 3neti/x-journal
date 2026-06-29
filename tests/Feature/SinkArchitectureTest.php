<?php

use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Contracts\SecondaryJournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalSinkDispatcher;
use Carbon\CarbonImmutable;

function sinkJournalEntryData(?string $referenceNumber = null): ExecutionJournalEntryData
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
        ),
        payload: ['status' => 'succeeded'],
        money: new ExecutionMoneyData(amount: '100.00', currency: 'PHP', minorAmount: 10000),
        metadata: ['source' => 'sink-test'],
        referenceNumber: $referenceNumber,
    );
}

class RecordingSecondaryJournalSink implements SecondaryJournalSinkContract
{
    public ?ExecutionJournalEntry $entry = null;

    public ?ExecutionJournalEntryData $data = null;

    public int $calls = 0;

    public function recordProjection(ExecutionJournalEntry $entry, ExecutionJournalEntryData $data): void
    {
        $this->entry = $entry;
        $this->data = $data;
        $this->calls++;
    }
}

it('binds the journal sink contract to the sink dispatcher', function () {
    expect(app(JournalSinkContract::class))->toBeInstanceOf(JournalSinkDispatcher::class);
});

it('keeps the database sink as the canonical default sink', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(sinkJournalEntryData());

    expect($entry)->toBeInstanceOf(ExecutionJournalEntry::class)
        ->and($entry->exists)->toBeTrue()
        ->and(ExecutionJournalEntry::query()->count())->toBe(1);
});

it('dispatches canonical entries to secondary sinks after database persistence', function () {
    $secondarySink = new RecordingSecondaryJournalSink;

    app(JournalSinkDispatcher::class)->addSecondarySink($secondarySink);

    $entry = app(ExecutionJournalRecorder::class)->record(sinkJournalEntryData());

    expect($secondarySink->calls)->toBe(1)
        ->and($secondarySink->entry?->is($entry))->toBeTrue()
        ->and($secondarySink->entry?->exists)->toBeTrue()
        ->and($secondarySink->data)->toBeInstanceOf(ExecutionJournalEntryData::class);
});

it('treats secondary sinks as projections and not canonical journal truth', function () {
    $secondarySink = new RecordingSecondaryJournalSink;

    app(JournalSinkDispatcher::class)->addSecondarySink($secondarySink);

    app(ExecutionJournalRecorder::class)->record(sinkJournalEntryData());

    expect($secondarySink->calls)->toBe(1)
        ->and(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->first()?->reference_number)->toBe('ERN-2026-000000001');
});

it('allows multiple secondary sinks to receive the same canonical entry', function () {
    $first = new RecordingSecondaryJournalSink;
    $second = new RecordingSecondaryJournalSink;

    app(JournalSinkDispatcher::class)
        ->addSecondarySink($first)
        ->addSecondarySink($second);

    $entry = app(ExecutionJournalRecorder::class)->record(sinkJournalEntryData());

    expect($first->entry?->is($entry))->toBeTrue()
        ->and($second->entry?->is($entry))->toBeTrue()
        ->and($first->data?->referenceNumber)->toBe('ERN-2026-000000001')
        ->and($second->data?->referenceNumber)->toBe('ERN-2026-000000001');
});
