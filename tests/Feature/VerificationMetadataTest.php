<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LBHurtado\XJournal\Contracts\JournalVerificationServiceContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

function verificationEntryData(
    ?string $referenceNumber = null,
    string $eventType = 'execution.result.recorded',
): ExecutionJournalEntryData {
    return new ExecutionJournalEntryData(
        eventType: $eventType,
        occurredAt: CarbonImmutable::parse('2026-06-29 10:15:00', 'UTC'),
        actor: new ExecutionActorData(id: 'system-1', type: 'system', name: 'System'),
        subject: new ExecutionSubjectData(id: 'voucher-1', type: 'voucher', display: 'Voucher 1'),
        references: new ExecutionReferenceData(executionId: 'exec-verify-1'),
        payload: ['status' => 'recorded'],
        metadata: ['source' => 'verification-test'],
        referenceNumber: $referenceNumber,
    );
}

it('builds verification metadata for recorded entries', function () {
    config()->set('x-journal.verification.base_url', 'https://example.test');
    config()->set('x-journal.verification.path', '/verify');
    config()->set('x-journal.verification.token_secret', 'verification-secret');

    $entry = app(ExecutionJournalRecorder::class)->record(verificationEntryData());
    $service = app(JournalVerificationServiceContract::class);

    $verification = $service->verify($entry);

    expect($verification->referenceNumber)->toBe($entry->reference_number)
        ->and($verification->verificationUrl)->toBe('https://example.test/verify/'.$entry->reference_number)
        ->and($verification->verificationToken)->not->toBeNull()
        ->and($verification->chainVerified)->toBeTrue()
        ->and($verification->receiptHash)->toBe((string) $entry->integrity['hash'])
        ->and($verification->levels)->toContain('verification_url', 'verification_token', 'receipt_hash', 'journal_chain')
        ->and($verification->status)->toBe('verified');
});

it('issues and validates scoped verification tokens', function () {
    config()->set('x-journal.verification.token_secret', 'verification-secret');

    $entry = app(ExecutionJournalRecorder::class)->record(verificationEntryData());
    $service = app(JournalVerificationServiceContract::class);
    $token = $service->tokenFor($entry);

    expect($token)->not->toBeNull()
        ->and($service->validateToken($entry->reference_number, $token))->toBeTrue()
        ->and($service->validateToken($entry->reference_number, 'x-journal|000|bad'))
            ->toBeFalse()
        ->and($service->validateToken('ERN-2026-000000999', $token))->toBeFalse();
});

it('detects broken integrity when chain verification fails', function () {
    config()->set('x-journal.verification.token_secret', 'verification-secret');

    $recorder = app(ExecutionJournalRecorder::class);
    $entry = $recorder->record(verificationEntryData());
    $service = app(JournalVerificationServiceContract::class);

    DB::table('execution_journal_entries')
        ->where('id', $entry->id)
        ->update(['integrity' => json_encode(['hash' => 'tampered'], JSON_THROW_ON_ERROR)]);

    $entry = $entry->fresh();
    $verification = $service->verify($entry);

    expect($verification->chainVerified)->toBeFalse()
        ->and($verification->status)->toBe('unverified')
        ->and($verification->levels)->not->toContain('journal_chain');
});

