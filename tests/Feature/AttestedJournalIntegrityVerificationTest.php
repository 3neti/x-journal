<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\AttestedJournalIntegrityVerifier;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalIntegrityExceptionAttestor;
use LBHurtado\XJournal\Services\JournalIntegrityVerifier;

it('attests immutable legacy issues and verifies the operational view', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(
        new ExecutionJournalEntryData(
            eventType: 'execution.result.recorded',
            occurredAt: CarbonImmutable::parse(
                '2026-07-26T12:00:00+08:00',
            ),
            actor: new ExecutionActorData(
                id: 'system',
                type: 'system',
            ),
            subject: new ExecutionSubjectData(
                id: 'legacy-fixture',
                type: 'test_fixture',
            ),
            references: new ExecutionReferenceData(
                executionId: 'legacy-fixture',
            ),
            idempotencyKey: 'legacy-fixture',
            payload: ['status' => 'succeeded'],
            metadata: ['source' => 'test'],
        ),
    );
    $integrity = $entry->integrity;
    $integrity['hash'] = 'legacy-noncanonical-hash';
    DB::table('execution_journal_entries')
        ->where('id', $entry->getKey())
        ->update([
            'integrity' => json_encode(
                $integrity,
                JSON_THROW_ON_ERROR,
            ),
        ]);
    $entry = $entry->fresh();
    $original = $entry->toArray();
    $base = app(JournalIntegrityVerifier::class)->verify();

    expect($base->verified)->toBeFalse()
        ->and($base->issues)->toHaveCount(1)
        ->and($base->issues[0]->code)->toBe('hash_mismatch');

    $preview = app(
        JournalIntegrityExceptionAttestor::class,
    )->inspect(
        [$entry->reference_number],
        'noncanonical_test_fixture',
    );

    expect($preview['status'])->toBe('ready')
        ->and($preview['committed'])->toBeFalse()
        ->and(ExecutionJournalEntry::query()->count())->toBe(1);

    $result = app(
        JournalIntegrityExceptionAttestor::class,
    )->attest(
        [$entry->reference_number],
        'noncanonical_test_fixture',
        'test-authorization-20260726',
    );

    expect($result['status'])->toBe('attested')
        ->and($result['original_entries_unchanged'])->toBeTrue()
        ->and($entry->fresh()?->toArray())->toBe($original)
        ->and(ExecutionJournalEntry::query()->count())->toBe(2)
        ->and(app(JournalIntegrityVerifier::class)
            ->verify()
            ->verified)->toBeFalse();

    $operational = app(
        AttestedJournalIntegrityVerifier::class,
    )->verify();

    expect($operational['verified'])->toBeTrue()
        ->and($operational['status'])
        ->toBe('verified_with_attested_legacy_exceptions')
        ->and($operational['base_verified'])->toBeFalse()
        ->and($operational['base_issue_count'])->toBe(1)
        ->and($operational['outstanding_issue_count'])->toBe(0)
        ->and($operational['attested_exception_count'])->toBe(1);

    $replay = app(
        JournalIntegrityExceptionAttestor::class,
    )->attest(
        [$entry->reference_number],
        'noncanonical_test_fixture',
        'test-authorization-20260726',
    );

    expect($replay['status'])->toBe('already_attested')
        ->and(ExecutionJournalEntry::query()->count())->toBe(2);
});

it('does not accept an attestation whose own journal entry is invalid', function () {
    $entry = app(ExecutionJournalRecorder::class)->record(
        new ExecutionJournalEntryData(
            eventType: 'execution.result.recorded',
            occurredAt: now(),
            actor: new ExecutionActorData(type: 'system'),
            subject: new ExecutionSubjectData(
                id: 'legacy-fixture',
                type: 'test_fixture',
            ),
            references: new ExecutionReferenceData,
            payload: [],
        ),
    );
    $integrity = $entry->integrity;
    $integrity['hash'] = 'legacy-noncanonical-hash';
    DB::table('execution_journal_entries')
        ->where('id', $entry->getKey())
        ->update([
            'integrity' => json_encode(
                $integrity,
                JSON_THROW_ON_ERROR,
            ),
        ]);
    $entry = $entry->fresh();
    app(JournalIntegrityExceptionAttestor::class)->attest(
        [$entry->reference_number],
        'legacy_canonicalization',
        'test-authorization-invalid-attestation',
    );
    $attestation = ExecutionJournalEntry::query()
        ->where(
            'event_type',
            'journal.integrity_exception.attested',
        )
        ->sole();
    $attestationIntegrity = $attestation->integrity;
    $attestationIntegrity['hash'] = 'invalid-attestation-hash';
    DB::table('execution_journal_entries')
        ->where('id', $attestation->getKey())
        ->update([
            'integrity' => json_encode(
                $attestationIntegrity,
                JSON_THROW_ON_ERROR,
            ),
        ]);

    $operational = app(
        AttestedJournalIntegrityVerifier::class,
    )->verify();

    expect($operational['verified'])->toBeFalse()
        ->and($operational['status'])->toBe('unverified')
        ->and($operational['outstanding_issue_count'])
        ->toBeGreaterThan(0);
});
