<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\ExecutionStatementSnapshotQueryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Models\ExecutionStatementSnapshot;
use Illuminate\Support\Facades\DB;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\ExecutionStatementSnapshotGenerator;
use LBHurtado\XJournal\Services\ExecutionStatementSnapshotHasher;
use LBHurtado\XJournal\Services\ExecutionStatementSnapshotRetriever;
use LBHurtado\XJournal\Services\ExecutionStatementSnapshotReconciler;
use LBHurtado\XJournal\Services\ExecutionStatementSnapshotVerifier;

function statementJournalEntryData(
    ?string $referenceNumber = null,
    string $subjectId = 'voucher-1',
    string $subjectType = 'voucher',
    ?CarbonImmutable $occurredAt = null,
): ExecutionJournalEntryData {
    return new ExecutionJournalEntryData(
        eventType: 'voucher.redeemed',
        occurredAt: $occurredAt ?? CarbonImmutable::parse('2026-06-29 10:15:00', 'UTC'),
        actor: new ExecutionActorData(id: '123', type: 'system', name: 'Journal Writer'),
        subject: new ExecutionSubjectData(id: $subjectId, type: $subjectType, display: "Voucher {$subjectId}"),
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

it('searches snapshots by statement and subject filters', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $recorder = app(ExecutionJournalRecorder::class);

    $entries = collect([$recorder->record(statementJournalEntryData())]);

    $matching = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-1',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 10:00:00', 'UTC'),
    );

    $generator->generate(
        statementType: 'program',
        subjectType: 'program',
        subjectId: 'program-1',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 10:10:00', 'UTC'),
    );

    $queryResult = app(ExecutionStatementSnapshotRetriever::class)->search(new ExecutionStatementSnapshotQueryData(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-1',
        limit: 10,
    ));

    expect($queryResult->total)->toBe(1)
        ->and($queryResult->snapshots->first()->is($matching))->toBeTrue();
});

it('supports snapshot lookup by statement number and latest-per-subject retrieval', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $recorder = app(ExecutionJournalRecorder::class);
    $entries = collect([$recorder->record(statementJournalEntryData())]);

    $first = $generator->generate(
        statementType: 'campaign',
        subjectType: 'campaign',
        subjectId: 'campaign-1',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 09:00:00', 'UTC'),
    );

    $second = $generator->generate(
        statementType: 'campaign',
        subjectType: 'campaign',
        subjectId: 'campaign-1',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-07-31 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-07-31 09:00:00', 'UTC'),
    );

    $retriever = app(ExecutionStatementSnapshotRetriever::class);

    expect($retriever->findByStatementNumber($first->statement_number)?->is($first))->toBeTrue()
        ->and($retriever->latestForSubject('campaign', 'campaign', 'campaign-1')?->is($second))->toBeTrue();
});

it('supports deterministic snapshot retrieval windows', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $recorder = app(ExecutionJournalRecorder::class);
    $entries = collect([$recorder->record(statementJournalEntryData())]);

    $first = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-2',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 10:00:00', 'UTC'),
    );

    $second = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-2',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-07-31 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-07-31 10:00:00', 'UTC'),
    );

    $window = app(ExecutionStatementSnapshotRetriever::class)->search(new ExecutionStatementSnapshotQueryData(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-2',
        limit: 1,
        offset: 1,
        order: 'asc',
    ));

    expect($window->total)->toBe(2)
        ->and($window->snapshots)->toHaveCount(1)
        ->and($window->snapshots->first()->is($second))->toBeTrue()
        ->and($window->hasMore())->toBeFalse();
});

it('does not mutate snapshots while searching', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $recorder = app(ExecutionJournalRecorder::class);
    $entries = collect([$recorder->record(statementJournalEntryData())]);

    $snapshot = $generator->generate(
        statementType: 'program',
        subjectType: 'program',
        subjectId: 'program-2',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
    );

    $original = $snapshot->fresh()?->toArray();
    app(ExecutionStatementSnapshotRetriever::class)->search(new ExecutionStatementSnapshotQueryData(statementType: 'program'));

    expect($snapshot->fresh()?->toArray())->toBe($original);
});

it('verifies a clean recovery snapshot chain', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $recorder = app(ExecutionJournalRecorder::class);
    $entries = collect([$recorder->record(statementJournalEntryData())]);

    $first = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-chain',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 09:00:00', 'UTC'),
    );

    $second = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-chain',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-07-31 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-07-31 10:00:00', 'UTC'),
    );

    $third = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-chain',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-08-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-08-31 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-08-31 10:00:00', 'UTC'),
    );

    expect($second->previous_hash)->toBe($first->hash)
        ->and($third->previous_hash)->toBe($second->hash)
        ->and(app(ExecutionStatementSnapshotRetriever::class)->verifyChainForQuery(
            new ExecutionStatementSnapshotQueryData(statementType: 'wallet', subjectType: 'wallet', subjectId: 'wallet-chain')
        ))->toBeTrue();
});

it('reports an invalid chain when a snapshot hash is tampered', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $recorder = app(ExecutionJournalRecorder::class);
    $entries = collect([$recorder->record(statementJournalEntryData())]);

    $first = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-tamper',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 09:00:00', 'UTC'),
    );

    DB::table('execution_statement_snapshots')
        ->where('id', $first->id)
        ->update(['payload_json' => json_encode(['notes' => 'tampered'], JSON_THROW_ON_ERROR)]);

    expect(app(ExecutionStatementSnapshotRetriever::class)->verifyChainForQuery(
        new ExecutionStatementSnapshotQueryData(statementType: 'wallet', subjectType: 'wallet', subjectId: 'wallet-tamper')
    ))->toBeFalse();
});

it('reports an invalid chain when previous hash links are broken', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $recorder = app(ExecutionJournalRecorder::class);
    $entries = collect([$recorder->record(statementJournalEntryData())]);

    $first = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-links',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 09:00:00', 'UTC'),
    );

    $second = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-links',
        entries: $entries,
        periodStart: CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-07-31 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-07-31 09:00:00', 'UTC'),
    );

    DB::table('execution_statement_snapshots')
        ->where('id', $second->id)
        ->update(['previous_hash' => 'broken-prev']);

    expect(app(ExecutionStatementSnapshotRetriever::class)->verifyChainForQuery(
        new ExecutionStatementSnapshotQueryData(statementType: 'wallet', subjectType: 'wallet', subjectId: 'wallet-links')
    ))->toBeFalse();
});

it('reconciles snapshot entries with period journal events', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $reconciler = app(ExecutionStatementSnapshotReconciler::class);
    $recorder = app(ExecutionJournalRecorder::class);

    $periodEntries = collect([
        $recorder->record(statementJournalEntryData(
            subjectId: 'wallet-reconcile',
            subjectType: 'wallet',
            occurredAt: CarbonImmutable::parse('2026-06-10 10:00:00', 'UTC'),
        )),
        $recorder->record(statementJournalEntryData(
            subjectId: 'wallet-reconcile',
            subjectType: 'wallet',
            occurredAt: CarbonImmutable::parse('2026-06-15 14:00:00', 'UTC'),
        )),
    ]);

    $recorder->record(statementJournalEntryData(
        subjectId: 'other-wallet',
        subjectType: 'wallet',
        occurredAt: CarbonImmutable::parse('2026-06-12 10:00:00', 'UTC'),
    ));

    $snapshot = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-reconcile',
        entries: $periodEntries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 10:00:00', 'UTC'),
    );

    $result = $reconciler->reconcile($snapshot);

    expect($result->isConsistent())->toBeTrue()
        ->and($result->actualEntriesCount)->toBe(2)
        ->and($result->expectedEntriesCount)->toBe(2)
        ->and($result->issues)->toBe([]);
});

it('flags snapshot mismatches when subject entries drift after snapshot creation', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $reconciler = app(ExecutionStatementSnapshotReconciler::class);
    $recorder = app(ExecutionJournalRecorder::class);

    $firstSnapshotEntries = collect([
        $recorder->record(statementJournalEntryData(
            subjectId: 'wallet-reconcile-bad',
            subjectType: 'wallet',
            occurredAt: CarbonImmutable::parse('2026-06-10 10:00:00', 'UTC'),
        )),
    ]);

    $snapshot = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-reconcile-bad',
        entries: $firstSnapshotEntries,
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 10:00:00', 'UTC'),
    );

    $recorder->record(statementJournalEntryData(
        subjectId: 'wallet-reconcile-bad',
        subjectType: 'wallet',
        occurredAt: CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'),
    ));

    $result = $reconciler->reconcile($snapshot);

    expect($result->isConsistent())->toBeFalse()
        ->and($result->actualEntriesCount)->toBe(2)
        ->and($result->expectedEntriesCount)->toBe(1)
        ->and($result->issues)->toContain('entries_count_mismatch')
        ->and($result->issues)->toContain('entries_hash_mismatch');
});

it('detects tampered journal entries without mutating entries or snapshots', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $reconciler = app(ExecutionStatementSnapshotReconciler::class);
    $verifier = app(ExecutionStatementSnapshotVerifier::class);
    $recorder = app(ExecutionJournalRecorder::class);

    $entry = $recorder->record(statementJournalEntryData(
        subjectId: 'wallet-entry-tamper',
        subjectType: 'wallet',
        occurredAt: CarbonImmutable::parse('2026-06-10 10:00:00', 'UTC'),
    ));

    $snapshot = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-entry-tamper',
        entries: collect([$entry]),
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 10:00:00', 'UTC'),
    );

    DB::table('execution_journal_entries')
        ->where('id', $entry->id)
        ->update(['payload' => json_encode(['status' => 'tampered'], JSON_THROW_ON_ERROR)]);

    $tamperedEntryBefore = ExecutionJournalEntry::query()->find($entry->id)?->toArray();
    $snapshotBefore = $snapshot->fresh()?->toArray();
    $reconciliation = $reconciler->reconcile($snapshot->fresh());
    $verification = $verifier->verifyAll(new ExecutionStatementSnapshotQueryData(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-entry-tamper',
    ));

    expect($reconciliation->isConsistent())->toBeFalse()
        ->and($reconciliation->issues)->toContain('entries_hash_mismatch')
        ->and($reconciliation->issues)->not->toContain('entries_count_mismatch')
        ->and($verification->isVerified())->toBeFalse()
        ->and(collect($verification->issues)->pluck('code')->all())->toContain('entries_hash_mismatch')
        ->and(ExecutionJournalEntry::query()->find($entry->id)?->toArray())->toBe($tamperedEntryBefore)
        ->and($snapshot->fresh()?->toArray())->toBe($snapshotBefore);
});

it('verifies a complete snapshot set through the recovery verifier', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $recorder = app(ExecutionJournalRecorder::class);
    $verifier = app(ExecutionStatementSnapshotVerifier::class);

    $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-verify-clean',
        entries: collect([
            $recorder->record(statementJournalEntryData(
                subjectId: 'wallet-verify-clean',
                subjectType: 'wallet',
                occurredAt: CarbonImmutable::parse('2026-06-10 10:00:00', 'UTC'),
            )),
        ]),
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 10:00:00', 'UTC'),
    );

    $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-verify-clean',
        entries: collect([
            $recorder->record(statementJournalEntryData(
                subjectId: 'wallet-verify-clean',
                subjectType: 'wallet',
                occurredAt: CarbonImmutable::parse('2026-07-10 10:00:00', 'UTC'),
            )),
        ]),
        periodStart: CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-07-31 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-07-31 10:00:00', 'UTC'),
    );

    $result = $verifier->verifyAll(new ExecutionStatementSnapshotQueryData(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-verify-clean',
    ));

    expect($result->isVerified())->toBeTrue()
        ->and($result->checkedSnapshotCount)->toBe(2)
        ->and($result->issues)->toBe([]);
});

it('reports structured recovery verifier issues for chain and replay mismatches', function () {
    $generator = app(ExecutionStatementSnapshotGenerator::class);
    $recorder = app(ExecutionJournalRecorder::class);
    $verifier = app(ExecutionStatementSnapshotVerifier::class);

    $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-verify-bad',
        entries: collect([
            $recorder->record(statementJournalEntryData(
                subjectId: 'wallet-verify-bad',
                subjectType: 'wallet',
                occurredAt: CarbonImmutable::parse('2026-06-10 10:00:00', 'UTC'),
            )),
        ]),
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-30 10:00:00', 'UTC'),
    );

    $second = $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-verify-bad',
        entries: collect([
            $recorder->record(statementJournalEntryData(
                subjectId: 'wallet-verify-bad',
                subjectType: 'wallet',
                occurredAt: CarbonImmutable::parse('2026-07-10 10:00:00', 'UTC'),
            )),
        ]),
        periodStart: CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-07-31 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-07-31 10:00:00', 'UTC'),
    );

    DB::table('execution_statement_snapshots')
        ->where('id', $second->id)
        ->update(['payload_json' => json_encode(['tampered' => true], JSON_THROW_ON_ERROR)]);

    $recorder->record(statementJournalEntryData(
        subjectId: 'wallet-verify-bad',
        subjectType: 'wallet',
        occurredAt: CarbonImmutable::parse('2026-07-15 12:00:00', 'UTC'),
    ));

    DB::table('execution_statement_snapshots')
        ->where('id', $second->id)
        ->update(['previous_hash' => 'broken-prev']);

    $result = $verifier->verifyAll(new ExecutionStatementSnapshotQueryData(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-verify-bad',
    ));

    $codes = collect($result->issues)->pluck('code')->all();

    expect($result->isVerified())->toBeFalse()
        ->and($result->checkedSnapshotCount)->toBe(2)
        ->and($codes)->toContain('hash_mismatch')
        ->and($codes)->toContain('previous_hash_mismatch')
        ->and($codes)->toContain('entries_count_mismatch')
        ->and($codes)->toContain('entries_hash_mismatch');
});
