<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\JournalIntegrityVerificationData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalIntegrityVerifier;

function verificationJournalEntryData(?string $referenceNumber = null): ExecutionJournalEntryData
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
        metadata: ['source' => 'verification-test'],
        referenceNumber: $referenceNumber,
    );
}

it('verifies a clean journal hash chain', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $recorder->record(verificationJournalEntryData());
    $recorder->record(verificationJournalEntryData());

    $verification = app(JournalIntegrityVerifier::class)->verify();

    expect($verification)->toBeInstanceOf(JournalIntegrityVerificationData::class)
        ->and($verification->verified)->toBeTrue()
        ->and($verification->checkedEntryCount)->toBe(2)
        ->and($verification->metadata['checked_entry_count'])->toBe(2)
        ->and($verification->metadata['issue_count'])->toBe(0)
        ->and($verification->metadata['issue_codes'])->toBe([])
        ->and($verification->metadata['first_reference_number'])->toBe('ERN-2026-000000001')
        ->and($verification->metadata['last_reference_number'])->toBe('ERN-2026-000000002')
        ->and($verification->issues)->toBe([]);
});

it('normalizes sub-second occurrence precision before hashing and persistence', function () {
    $entryData = verificationJournalEntryData()->withOccurredAt(
        CarbonImmutable::parse(
            '2026-06-29T10:15:00.123456Z',
        ),
    );
    $recorder = app(ExecutionJournalRecorder::class);
    $entry = $recorder->record($entryData);

    expect($entry->occurred_at->format('u'))->toBe('000000')
        ->and(app(JournalIntegrityVerifier::class)->verify()->verified)
        ->toBeTrue();
});

it('detects canonical payload tampering', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(verificationJournalEntryData());

    DB::table('execution_journal_entries')
        ->where('id', $entry->id)
        ->update(['payload' => json_encode(['status' => 'tampered'], JSON_THROW_ON_ERROR)]);

    $verification = app(JournalIntegrityVerifier::class)->verify();

    expect($verification->verified)->toBeFalse()
        ->and($verification->checkedEntryCount)->toBe(1)
        ->and($verification->metadata['issue_count'])->toBe(1)
        ->and($verification->metadata['issue_codes'])->toContain('hash_mismatch')
        ->and($verification->issues)->toHaveCount(1)
        ->and($verification->issues[0]->code)->toBe('hash_mismatch')
        ->and($verification->issues[0]->referenceNumber)->toBe('ERN-2026-000000001');
});

it('detects broken hash chain continuity', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $first = $recorder->record(verificationJournalEntryData());
    $second = $recorder->record(verificationJournalEntryData());
    $integrity = $second->integrity;
    $integrity['previous_hash'] = 'broken-previous-hash';

    DB::table('execution_journal_entries')
        ->where('id', $second->id)
        ->update(['integrity' => json_encode($integrity, JSON_THROW_ON_ERROR)]);

    $verification = app(JournalIntegrityVerifier::class)->verify();
    $codes = collect($verification->issues)->pluck('code')->all();

    expect($second->integrity['previous_hash'])->toBe($first->integrity['hash'])
        ->and($verification->verified)->toBeFalse()
        ->and($codes)->toContain('previous_hash_mismatch')
        ->and($codes)->toContain('hash_mismatch');
});

it('detects missing integrity hashes without throwing', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(verificationJournalEntryData());
    $integrity = $entry->integrity;
    unset($integrity['hash']);

    DB::table('execution_journal_entries')
        ->where('id', $entry->id)
        ->update(['integrity' => json_encode($integrity, JSON_THROW_ON_ERROR)]);

    $verification = app(JournalIntegrityVerifier::class)->verify();

    expect($verification->verified)->toBeFalse()
        ->and($verification->issues)->toHaveCount(1)
        ->and($verification->issues[0]->code)->toBe('missing_hash');
});

it('does not mutate journal entries while verifying integrity', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(verificationJournalEntryData());
    $original = $entry->fresh()?->toArray();

    app(JournalIntegrityVerifier::class)->verify();

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($entry->fresh()?->toArray())->toBe($original);
});

it('does not require signatures in the phase six baseline', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(verificationJournalEntryData());

    expect($entry->integrity['signature'])->toBeNull()
        ->and(app(JournalIntegrityVerifier::class)->verify()->verified)->toBeTrue();
});
