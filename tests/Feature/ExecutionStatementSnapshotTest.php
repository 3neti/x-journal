<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionStatementSnapshot;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\ExecutionStatementSnapshotGenerator;
use LBHurtado\XJournal\Services\ExecutionStatementSnapshotHasher;

function statementJournalEntryData(
    ?string $referenceNumber = null,
    string $subjectId = 'voucher-1',
): ExecutionJournalEntryData {
    return new ExecutionJournalEntryData(
        eventType: 'voucher.redeemed',
        occurredAt: CarbonImmutable::parse('2026-06-29 10:15:00', 'UTC'),
        actor: new ExecutionActorData(id: '123', type: 'system', name: 'Journal Writer'),
        subject: new ExecutionSubjectData(id: $subjectId, type: 'voucher', display: "Voucher {$subjectId}"),
        references: new ExecutionReferenceData(
            correlationId: 'corr-1',
            causationId: 'cause-1',
            executionId: 'exec-1',
            providerReference: 'provider-1',
        ),
        payload: ['status' => 'succeeded'],
        money: new ExecutionMoneyData(amount: '100.00', currency: 'PHP', minorAmount: 10000),
        metadata: ['source' => 'snapshot-test'],
        referenceNumber: $referenceNumber,
    );
}

it('captures statement snapshots from journal entries', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $entries = collect([
        $recorder->record(statementJournalEntryData()),
        $recorder->record(statementJournalEntryData(subjectId: 'voucher-2')),
    ]);

    $snapshot = app(ExecutionStatementSnapshotGenerator::class)->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-1',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        openingJson: ['balance' => '200.00'],
        activityJson: ['redeemed_count' => 2],
        closingJson: ['balance' => '150.00'],
        payloadJson: ['notes' => 'wallet statement'],
        generatedByType: 'system',
        generatedById: 'statement-service',
        generatedAt: CarbonImmutable::parse('2026-06-29 10:20:00', 'UTC'),
    );

    expect($snapshot)->toBeInstanceOf(ExecutionStatementSnapshot::class)
        ->and($snapshot->statement_number)->toBe('STM-2026-000000001')
        ->and($snapshot->statement_type)->toBe('wallet')
        ->and($snapshot->subject_type)->toBe('wallet')
        ->and($snapshot->subject_id)->toBe('wallet-1')
        ->and($snapshot->entries_count)->toBe(2)
        ->and($snapshot->generated_by_type)->toBe('system')
        ->and($snapshot->payload_json['notes'])->toBe('wallet statement')
        ->and($snapshot->entries_hash)->toBe(app(ExecutionStatementSnapshotHasher::class)->entriesHash($entries));
});

it('chains snapshot hashes so each snapshot anchors the previous one', function () {
    $recorder = app(ExecutionJournalRecorder::class);
    $generator = app(ExecutionStatementSnapshotGenerator::class);

    $firstEntries = collect([
        $recorder->record(statementJournalEntryData()),
    ]);

    $secondEntries = collect([
        $recorder->record(statementJournalEntryData()),
    ]);

    $first = $generator->generate(
        statementType: 'program',
        subjectType: 'program',
        subjectId: 'program-1',
        entries: $firstEntries,
        periodStart: CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-07-01 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-07-01 01:00:00', 'UTC'),
    );

    $second = $generator->generate(
        statementType: 'program',
        subjectType: 'program',
        subjectId: 'program-1',
        entries: $secondEntries,
        periodStart: CarbonImmutable::parse('2026-07-02 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-07-02 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-07-02 01:00:00', 'UTC'),
    );

    expect($second->previous_hash)->toBe($first->hash)
        ->and($second->statement_number)->toBe('STM-2026-000000002')
        ->and(ExecutionStatementSnapshot::query()->count())->toBe(2);
});

it('supports explicit statement numbers and empty entry sets for snapshots', function () {
    $snapshot = app(ExecutionStatementSnapshotGenerator::class)->generate(
        statementType: 'issuer',
        subjectType: 'issuer',
        subjectId: 'issuer-1',
        entries: [],
        periodStart: CarbonImmutable::parse('2026-08-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-08-31 23:59:59', 'UTC'),
        statementNumber: 'STM-ISS-0001',
        generatedAt: CarbonImmutable::parse('2026-08-01 00:00:00', 'UTC'),
    );

    expect($snapshot->statement_number)->toBe('STM-ISS-0001')
        ->and($snapshot->entries_count)->toBe(0)
        ->and($snapshot->entries_hash)->toBeString()
        ->and($snapshot->hash)->toBeString();
});
