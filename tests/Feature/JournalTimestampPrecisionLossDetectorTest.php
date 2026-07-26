<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionIntegrityData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalIdempotencyHasher;
use LBHurtado\XJournal\Services\ExecutionJournalIntegrityHasher;
use LBHurtado\XJournal\Services\JournalTimestampPrecisionLossDetector;

it('proves legacy timestamp precision loss with integrity and idempotency hashes', function () {
    $occurredAt = CarbonImmutable::parse(
        '2026-07-26T10:46:13.654321+08:00',
    );
    $entryData = new ExecutionJournalEntryData(
        eventType: 'account_funding.pay_code.inspected',
        occurredAt: $occurredAt,
        actor: new ExecutionActorData(id: '5', type: 'App\\Models\\User'),
        subject: new ExecutionSubjectData(
            id: '375',
            type: 'voucher',
            display: 'Account Funding Pay Code',
        ),
        references: new ExecutionReferenceData(
            correlationId: 'account-funding-inspection:test',
            executionId: '375',
        ),
        idempotencyKey: 'inspection:test',
        payload: [
            'status' => 'eligible',
            'inspection_token_persisted' => false,
            'raw_pay_code_persisted' => false,
        ],
        metadata: [
            'schema' => 'x-change.account-funding-pay-code-journal.v1',
            'domain' => 'account_funding',
            'source' => 'cockpit_account_funding_pay_code_inspection',
            'accounting_authority' => 'treasury_position_operations',
        ],
        referenceNumber: 'ERN-2026-000000007',
    );
    $integrityHasher = app(ExecutionJournalIntegrityHasher::class);
    $idempotencyHasher = app(ExecutionJournalIdempotencyHasher::class);
    $integrity = (new ExecutionIntegrityData(
        hash: $integrityHasher->hash(
            $entryData,
            ['previous_hash' => null],
        ),
        previousHash: null,
    ))->toArray();
    $entryId = DB::table('execution_journal_entries')->insertGetId([
        'reference_number' => $entryData->referenceNumber,
        'event_type' => $entryData->eventType,
        'occurred_at' => $occurredAt->utc()->format('Y-m-d H:i:s'),
        'actor_type' => $entryData->actor->type,
        'actor_id' => $entryData->actor->id,
        'subject_type' => $entryData->subject->type,
        'subject_id' => $entryData->subject->id,
        'correlation_id' => $entryData->references->correlationId,
        'causation_id' => null,
        'execution_id' => $entryData->references->executionId,
        'actor' => json_encode($entryData->actor->toArray(), JSON_THROW_ON_ERROR),
        'subject' => json_encode($entryData->subject->toArray(), JSON_THROW_ON_ERROR),
        'money' => null,
        'references' => json_encode($entryData->references->toArray(), JSON_THROW_ON_ERROR),
        'payload' => json_encode($entryData->payload, JSON_THROW_ON_ERROR),
        'integrity' => json_encode($integrity, JSON_THROW_ON_ERROR),
        'metadata' => json_encode($entryData->metadata, JSON_THROW_ON_ERROR),
        'idempotency_key' => $entryData->idempotencyKey,
        'idempotency_fingerprint' => $idempotencyHasher->fingerprint(
            $entryData,
        ),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $entry = ExecutionJournalEntry::query()->findOrFail($entryId);
    $before = $entry->toArray();
    $proof = app(
        JournalTimestampPrecisionLossDetector::class,
    )->prove($entry);

    expect($proof->proved)->toBeTrue()
        ->and($proof->candidateCount)->toBe(1)
        ->and($proof->recoveredMicroseconds)->toBe(654321)
        ->and($proof->idempotencyFingerprintMatched)->toBeTrue()
        ->and($proof->recoveredOccurredAt)
        ->toBe('2026-07-26T02:46:13.654321Z')
        ->and($entry->fresh()?->toArray())->toBe($before);
});

it('rejects a mismatch that cannot be explained only by timestamp precision', function () {
    $entry = ExecutionJournalEntry::query()->create([
        'reference_number' => 'ERN-2026-000000008',
        'event_type' => 'account_funding.pay_code.inspected',
        'occurred_at' => now()->startOfSecond(),
        'actor' => [],
        'subject' => [],
        'references' => [],
        'payload' => [],
        'integrity' => [
            'hash' => str_repeat('a', 64),
            'previous_hash' => null,
        ],
        'metadata' => [],
        'idempotency_key' => 'inspection:unproved',
        'idempotency_fingerprint' => str_repeat('b', 64),
    ]);

    $proof = app(
        JournalTimestampPrecisionLossDetector::class,
    )->prove($entry);

    expect($proof->proved)->toBeFalse()
        ->and($proof->candidateCount)->toBe(0)
        ->and($proof->recoveredOccurredAt)->toBeNull();
});
