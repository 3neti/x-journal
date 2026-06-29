<?php

use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionIntegrityData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;

it('normalizes actor data to the canonical shape', function () {
    expect(ExecutionActorData::fromArray([
        'id' => 123,
        'type' => 'user',
        'name' => '',
        'metadata' => 'invalid',
    ])->toArray())->toBe([
        'id' => '123',
        'type' => 'user',
        'name' => null,
        'metadata' => [],
    ]);
});

it('normalizes subject data to the canonical shape', function () {
    expect(ExecutionSubjectData::fromArray([
        'id' => 456,
        'type' => 'voucher',
        'display' => 'Voucher #456',
    ])->toArray())->toBe([
        'id' => '456',
        'type' => 'voucher',
        'display' => 'Voucher #456',
        'metadata' => [],
    ]);
});

it('normalizes money data to the canonical shape', function () {
    expect(ExecutionMoneyData::fromArray([
        'amount' => 100,
        'currency' => 'PHP',
        'minor_amount' => '10000',
    ])->toArray())->toBe([
        'amount' => '100',
        'currency' => 'PHP',
        'minor_amount' => 10000,
        'metadata' => [],
    ]);
});

it('normalizes reference data to the canonical shape', function () {
    expect(ExecutionReferenceData::fromArray([
        'correlation_id' => 1001,
        'causation_id' => '',
        'execution_id' => 'exec-1',
        'provider_reference' => null,
        'external_reference' => 'external-1',
    ])->toArray())->toBe([
        'correlation_id' => '1001',
        'causation_id' => null,
        'execution_id' => 'exec-1',
        'provider_reference' => null,
        'external_reference' => 'external-1',
        'metadata' => [],
    ]);
});

it('normalizes integrity data to the canonical shape', function () {
    expect(ExecutionIntegrityData::fromArray([
        'hash' => 'hash-1',
        'previous_hash' => '',
        'signature' => false,
    ])->toArray())->toBe([
        'hash' => 'hash-1',
        'previous_hash' => null,
        'signature' => null,
        'metadata' => [],
    ]);
});

it('normalizes journal entry data to the canonical shape', function () {
    $entry = ExecutionJournalEntryData::fromArray([
        'reference_number' => 'ERN-2026-000000001',
        'event_type' => 'voucher.redeemed',
        'occurred_at' => '2026-06-29T10:15:00Z',
        'actor' => ['id' => 123, 'type' => 'user'],
        'subject' => ['id' => 456, 'type' => 'voucher'],
        'money' => ['amount' => 100, 'currency' => 'PHP'],
        'references' => ['execution_id' => 'exec-1'],
        'payload' => ['status' => 'succeeded'],
        'metadata' => ['source' => 'test'],
    ]);

    expect($entry->toArray())->toBe([
        'reference_number' => 'ERN-2026-000000001',
        'event_type' => 'voucher.redeemed',
        'occurred_at' => '2026-06-29T10:15:00.000000Z',
        'actor' => [
            'id' => '123',
            'type' => 'user',
            'name' => null,
            'metadata' => [],
        ],
        'subject' => [
            'id' => '456',
            'type' => 'voucher',
            'display' => null,
            'metadata' => [],
        ],
        'money' => [
            'amount' => '100',
            'currency' => 'PHP',
            'minor_amount' => null,
            'metadata' => [],
        ],
        'references' => [
            'correlation_id' => null,
            'causation_id' => null,
            'execution_id' => 'exec-1',
            'provider_reference' => null,
            'external_reference' => null,
            'metadata' => [],
        ],
        'payload' => ['status' => 'succeeded'],
        'integrity' => [
            'hash' => null,
            'previous_hash' => null,
            'signature' => null,
            'metadata' => [],
        ],
        'metadata' => ['source' => 'test'],
    ]);
});
