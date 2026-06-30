<?php

use LBHurtado\XJournal\Exceptions\JournalEntryIdempotencyConflictException;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Contracts\JournalIdempotencyKeyResolverContract;
use LBHurtado\XJournal\Services\ExecutionJournalIdempotencyHasher;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionReferenceNumberGenerator;

function idempotentJournalEntryData(
    ?string $referenceNumber = null,
    ?string $idempotencyKey = 'idemp-dup-1',
    array $overrides = [],
): ExecutionJournalEntryData {
    return new ExecutionJournalEntryData(
        eventType: 'voucher.redeemed',
        occurredAt: CarbonImmutable::parse('2026-06-29 10:15:00', 'UTC'),
        actor: new ExecutionActorData(id: 123, type: 'system', name: 'Journal Writer'),
        subject: new ExecutionSubjectData(id: 'voucher-1', type: 'voucher', display: 'Voucher #1'),
        references: new ExecutionReferenceData(
            correlationId: 'corr-1',
            causationId: 'cause-1',
            executionId: 'exec-1',
            providerReference: 'provider-1',
        ),
        idempotencyKey: $idempotencyKey,
        payload: array_merge(['status' => 'succeeded'], $overrides['payload'] ?? []),
        money: new ExecutionMoneyData(amount: '100.00', currency: 'PHP', minorAmount: 10000),
        metadata: array_merge(['source' => 'idempotency-test'], $overrides['metadata'] ?? []),
        referenceNumber: $referenceNumber,
    );
}

it('returns the existing canonical entry when idempotency keys match and payload is stable', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $first = $recorder->record(idempotentJournalEntryData());
    $second = $recorder->record(idempotentJournalEntryData());

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->reference_number)->toBe($first->reference_number)
        ->and($second->idempotency_key)->toBe('idemp-dup-1');
});

it('throws a conflict when idempotency keys match but normalized facts differ', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $recorder->record(idempotentJournalEntryData());

    expect(fn () => $recorder->record(
        idempotentJournalEntryData(null, 'idemp-dup-1', ['payload' => ['status' => 'failed']])
    ))->toThrow(JournalEntryIdempotencyConflictException::class);
});

it('uses explicit references consistently with existing idempotency keys', function () {
    $recorder = app(ExecutionJournalRecorder::class);

    $existing = $recorder->record(idempotentJournalEntryData('ERN-2026-000000123', 'idemp-explicit-1'));
    $replayed = $recorder->record(idempotentJournalEntryData('ERN-2026-999999999', 'idemp-explicit-1'));

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($replayed->id)->toBe($existing->id)
        ->and($replayed->reference_number)->toBe('ERN-2026-000000123');
});

it('supports scoped idempotency keys through a configurable resolver', function () {
    $resolver = new class implements JournalIdempotencyKeyResolverContract {
        public function resolve(?string $idempotencyKey, ExecutionJournalEntryData $entry): ?string
        {
            if ($idempotencyKey === null) {
                return null;
            }

            $tenant = $entry->metadata['tenant_id'] ?? 'tenant-unknown';

            return $tenant.'|'.$idempotencyKey;
        }
    };

    $recorder = new ExecutionJournalRecorder(
        app(JournalSinkContract::class),
        app(ExecutionReferenceNumberGenerator::class),
        app(ExecutionJournalIdempotencyHasher::class),
        $resolver,
    );

    $tenantAEntry = $recorder->record(idempotentJournalEntryData(
        idempotencyKey: 'idemp-scoped-1',
        overrides: ['metadata' => ['tenant_id' => 'tenant-a']]
    ));
    $tenantBEntry = $recorder->record(idempotentJournalEntryData(
        idempotencyKey: 'idemp-scoped-1',
        overrides: ['metadata' => ['tenant_id' => 'tenant-b']]
    ));
    $tenantAReplay = $recorder->record(idempotentJournalEntryData(
        idempotencyKey: 'idemp-scoped-1',
        overrides: ['metadata' => ['tenant_id' => 'tenant-a']]
    ));

    expect(ExecutionJournalEntry::query()->count())->toBe(2)
        ->and($tenantAEntry->id)->toBe($tenantAReplay->id)
        ->and($tenantBEntry->id)->not->toBe($tenantAEntry->id)
        ->and($tenantAEntry->idempotency_key)->toBe('tenant-a|idemp-scoped-1')
        ->and($tenantBEntry->idempotency_key)->toBe('tenant-b|idemp-scoped-1');
});
