<?php

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

function verificationCommandEntryData(string $referenceNumber = 'ERM-000000001'): ExecutionJournalEntryData
{
    static $counter = 0;
    $counter++;

    return new ExecutionJournalEntryData(
        eventType: 'execution.result.recorded',
        occurredAt: CarbonImmutable::parse('2026-06-29 10:15:00', 'UTC')->addSeconds($counter),
        actor: new ExecutionActorData(id: 'system-1', type: 'system', name: 'System'),
        subject: new ExecutionSubjectData(id: 'voucher-'.$counter, type: 'voucher', display: "Voucher {$counter}"),
        references: new ExecutionReferenceData(executionId: 'exec-verify-command-'.$counter),
        payload: ['status' => 'recorded'],
        metadata: ['source' => 'verification-command-test'],
        referenceNumber: $referenceNumber,
    );
}

it('runs integrity verification command for full journal stream', function () {
    $recorder = app(ExecutionJournalRecorder::class);
    $recorder->record(verificationCommandEntryData('VN-1'));
    $recorder->record(verificationCommandEntryData('VN-2'));

    $result = $this->artisan('x-journal:verify-integrity');

    $result->assertExitCode(0)
        ->expectsOutputToContain('Journal integrity status: verified')
        ->expectsOutputToContain('Checked entries: 2')
        ->assertSuccessful();
});

it('runs integrity verification command for a single reference window', function () {
    $recorder = app(ExecutionJournalRecorder::class);
    $first = $recorder->record(verificationCommandEntryData('VN-1'));
    $second = $recorder->record(verificationCommandEntryData('VN-2'));

    $result = $this->artisan('x-journal:verify-integrity', ['reference' => $first->reference_number]);

    $result->assertExitCode(0)
        ->expectsOutputToContain('Checked entries: 1')
        ->expectsOutputToContain('No issues found.');
});

it('returns JSON from integrity verification command when requested', function () {
    $recorder = app(ExecutionJournalRecorder::class);
    $recorder->record(verificationCommandEntryData('VN-1'));

    $this->withoutMockingConsoleOutput();
    $exitCode = $this->artisan('x-journal:verify-integrity', ['--json' => true]);
    $output = trim($this->app->make(Kernel::class)->output());
    $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['verified'])->toBeTrue()
        ->and($payload['checked_entry_count'])->toBe(1);
});

it('fails clearly when the referenced entry does not exist', function () {
    $this->artisan('x-journal:verify-integrity', ['reference' => 'MISSING'])
        ->assertExitCode(1)
        ->expectsOutputToContain('Reference MISSING not found');
});

it('fails non-zero when verification issues are detected', function () {
    $recorder = app(ExecutionJournalRecorder::class);
    $entry = $recorder->record(verificationCommandEntryData('VN-1'));

    DB::table('execution_journal_entries')
        ->where('id', $entry->id)
        ->update(['integrity' => json_encode(['hash' => 'not-hash'], JSON_THROW_ON_ERROR)]);

    $this->artisan('x-journal:verify-integrity', ['reference' => $entry->reference_number])
        ->assertExitCode(1)
        ->expectsOutputToContain('Found 1 issue(s).')
        ->expectsOutputToContain('hash_mismatch');
});
