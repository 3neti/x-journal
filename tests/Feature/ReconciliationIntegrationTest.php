<?php

use LBHurtado\XJournal\Data\JournalRetrievalQueryData;
use LBHurtado\XJournal\Data\ReconciliationEventData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use LBHurtado\XJournal\Services\ReconciliationJournalRecorder;
use LBHurtado\XJournal\Transformers\ReconciliationJournalTransformer;

function reconciliationEvent(array $overrides = []): ReconciliationEventData
{
    return ReconciliationEventData::fromArray(array_replace([
        'event_type' => 'reconciliation.comparison.recorded',
        'occurred_at' => '2026-06-29T10:15:00Z',
        'actor' => [
            'id' => 'reconciliation-worker',
            'type' => 'system',
            'name' => 'Reconciliation Worker',
        ],
        'subject' => [
            'id' => 'voucher-1',
            'type' => 'voucher',
            'display' => 'Voucher #1',
        ],
        'references' => [
            'correlation_id' => 'recon-corr-1',
            'causation_id' => 'provider-ref-1',
            'execution_id' => 'exec-1',
            'provider_reference' => 'provider-ref-1',
            'external_reference' => 'bank-trace-1',
        ],
        'expected' => [
            'minor_amount' => 10000,
            'currency' => 'PHP',
            'status' => 'succeeded',
        ],
        'actual' => [
            'minor_amount' => 9500,
            'currency' => 'PHP',
            'status' => 'settled',
        ],
        'comparison' => [
            'matched' => false,
            'difference_minor_amount' => -500,
        ],
        'metadata' => [
            'batch_id' => 'recon-batch-1',
        ],
    ], $overrides));
}

it('normalizes reconciliation comparison payloads without resolving discrepancies', function () {
    $event = reconciliationEvent();
    $journalEvent = $event->toJournalEvent();

    expect($journalEvent->eventType)->toBe('reconciliation.comparison.recorded')
        ->and($journalEvent->references->executionId)->toBe('exec-1')
        ->and($journalEvent->references->providerReference)->toBe('provider-ref-1')
        ->and($journalEvent->payload)->toMatchArray([
            'expected' => [
                'minor_amount' => 10000,
                'currency' => 'PHP',
                'status' => 'succeeded',
            ],
            'actual' => [
                'minor_amount' => 9500,
                'currency' => 'PHP',
                'status' => 'settled',
            ],
            'comparison' => [
                'matched' => false,
                'difference_minor_amount' => -500,
            ],
        ])
        ->and($journalEvent->metadata)->toMatchArray([
            'source' => 'reconciliation',
            'integration' => 'reconciliation',
            'batch_id' => 'recon-batch-1',
        ]);
});

it('records reconciliation comparisons as journal facts', function () {
    $entry = app(ReconciliationJournalRecorder::class)->record(reconciliationEvent());

    expect($entry->reference_number)->toBe('ERN-2026-000000001')
        ->and($entry->event_type)->toBe('reconciliation.comparison.recorded')
        ->and($entry->actor_type)->toBe('system')
        ->and($entry->execution_id)->toBe('exec-1')
        ->and($entry->references['provider_reference'])->toBe('provider-ref-1')
        ->and($entry->payload['comparison']['matched'])->toBeFalse()
        ->and($entry->metadata['domain'])->toBe('reconciliation')
        ->and($entry->metadata['transformer'])->toBe(ReconciliationJournalTransformer::class);
});

it('preserves discrepancy facts without deciding correction or settlement outcome', function () {
    $entry = app(ReconciliationJournalRecorder::class)->record(reconciliationEvent([
        'event_type' => 'reconciliation.discrepancy.detected',
        'payload' => [
            'provider_file' => 'settlement-file-1.csv',
        ],
    ]));

    expect($entry->payload)->toMatchArray([
        'expected' => [
            'minor_amount' => 10000,
            'currency' => 'PHP',
            'status' => 'succeeded',
        ],
        'actual' => [
            'minor_amount' => 9500,
            'currency' => 'PHP',
            'status' => 'settled',
        ],
        'comparison' => [
            'matched' => false,
            'difference_minor_amount' => -500,
        ],
        'provider_file' => 'settlement-file-1.csv',
    ])
        ->and($entry->metadata)->not->toHaveKey('settlement_decision')
        ->and($entry->metadata)->not->toHaveKey('correction_decision')
        ->and($entry->metadata)->not->toHaveKey('next_action');
});

it('makes reconciliation entries retrievable by execution and provider references', function () {
    app(ReconciliationJournalRecorder::class)->record(reconciliationEvent());

    $byExecution = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'execution_id' => 'exec-1',
    ]));
    $byProvider = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'causation_id' => 'provider-ref-1',
    ]));

    expect($byExecution->total)->toBe(1)
        ->and($byProvider->total)->toBe(1)
        ->and($byProvider->entries->first()?->event_type)->toBe('reconciliation.comparison.recorded');
});

it('does not mutate supplied reconciliation event data while recording', function () {
    $event = reconciliationEvent();
    $before = $event->eventPayload();

    app(ReconciliationJournalRecorder::class)->record($event);

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($event->eventPayload())->toBe($before);
});
