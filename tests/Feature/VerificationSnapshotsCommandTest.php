<?php

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionStatementSnapshot;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\ExecutionStatementSnapshotGenerator;

function snapshotCommandEntryData(
    string $subjectId = 'wallet-1',
    string $subjectType = 'wallet',
): ExecutionJournalEntryData {
    return new ExecutionJournalEntryData(
        eventType: 'voucher.redeemed',
        occurredAt: CarbonImmutable::parse('2026-06-29 10:15:00', 'UTC'),
        actor: new ExecutionActorData(id: 'system-1', type: 'system', name: 'System'),
        subject: new ExecutionSubjectData(id: $subjectId, type: $subjectType, display: 'Wallet'),
        references: new ExecutionReferenceData(executionId: 'exec-1'),
        payload: ['status' => 'recorded'],
        metadata: ['source' => 'snapshot-command-test'],
    );
}

function snapshotCommandSeedSnapshots(): void
{
    $recorder = app(ExecutionJournalRecorder::class);
    $generator = app(ExecutionStatementSnapshotGenerator::class);

    $generator->generate(
        statementType: 'wallet',
        subjectType: 'wallet',
        subjectId: 'wallet-1',
        entries: collect([$recorder->record(snapshotCommandEntryData())]),
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-29 10:20:00', 'UTC'),
    );

    $generator->generate(
        statementType: 'program',
        subjectType: 'program',
        subjectId: 'program-1',
        entries: collect([$recorder->record(snapshotCommandEntryData('program-1', 'program'))]),
        periodStart: CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        periodEnd: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC'),
        generatedAt: CarbonImmutable::parse('2026-06-29 10:21:00', 'UTC'),
    );
}

it('runs snapshot verification for full snapshot stream', function () {
    snapshotCommandSeedSnapshots();
    $result = $this->artisan('x-journal:verify-snapshots');

    $result->assertExitCode(0)
        ->expectsOutputToContain('Snapshot verification status: verified')
        ->expectsOutputToContain('Checked snapshots: 2')
        ->expectsOutputToContain('No issues found.');
});

it('supports scoped snapshot verification filters', function () {
    snapshotCommandSeedSnapshots();

    $result = $this->artisan('x-journal:verify-snapshots', ['--subject-type' => 'wallet']);

    $result->assertExitCode(0)
        ->expectsOutputToContain('Checked snapshots: 1')
        ->expectsOutputToContain('No issues found.');
});

it('returns JSON from snapshot verification command when requested', function () {
    snapshotCommandSeedSnapshots();

    $this->withoutMockingConsoleOutput();
    $exitCode = $this->artisan('x-journal:verify-snapshots', ['--json' => true]);
    $output = trim($this->app->make(Kernel::class)->output());
    $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['verified'])->toBeTrue()
        ->and($payload['checked_snapshot_count'])->toBe(2);
});

it('returns non-zero when snapshot verification fails', function () {
    snapshotCommandSeedSnapshots();

    $snapshot = ExecutionStatementSnapshot::query()->orderBy('id', 'desc')->firstOrFail();
    DB::table('execution_statement_snapshots')->where('id', $snapshot->id)->update(['hash' => 'tampered']);

    $this->artisan('x-journal:verify-snapshots', ['--subject-id' => $snapshot->subject_id])
        ->assertExitCode(1)
        ->expectsOutputToContain('Found 1 issue(s).')
        ->expectsOutputToContain('hash_mismatch');
});
