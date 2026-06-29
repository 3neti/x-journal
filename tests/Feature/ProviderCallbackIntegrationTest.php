<?php

use LBHurtado\XJournal\Data\JournalRetrievalQueryData;
use LBHurtado\XJournal\Data\ProviderCallbackData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use LBHurtado\XJournal\Services\ProviderCallbackJournalRecorder;
use LBHurtado\XJournal\Transformers\ProviderCallbackJournalTransformer;

function providerCallback(array $overrides = []): ProviderCallbackData
{
    return ProviderCallbackData::fromArray(array_replace([
        'provider' => 'netbank',
        'provider_reference' => 'provider-ref-1',
        'raw_status' => 'P01',
        'received_payload' => [
            'status_code' => 'P01',
            'message' => 'Queued for processing',
        ],
        'occurred_at' => '2026-06-29T10:15:00Z',
        'actor' => [
            'id' => 'netbank',
            'type' => 'provider',
            'name' => 'Netbank',
        ],
        'subject' => [
            'id' => 'voucher-1',
            'type' => 'voucher',
            'display' => 'Voucher #1',
        ],
        'references' => [
            'correlation_id' => 'callback-corr-1',
            'causation_id' => 'provider-ref-1',
            'execution_id' => 'exec-1',
            'provider_reference' => 'provider-ref-1',
            'external_reference' => 'external-ref-1',
        ],
        'payload' => [
            'status' => 'pending',
        ],
        'metadata' => [
            'channel' => 'webhook',
        ],
    ], $overrides));
}

it('normalizes provider callback payloads without interpreting provider state', function () {
    $callback = providerCallback();
    $event = $callback->toJournalEvent();

    expect($event->eventType)->toBe('provider.callback.received')
        ->and($event->actor->type)->toBe('provider')
        ->and($event->references->executionId)->toBe('exec-1')
        ->and($event->references->providerReference)->toBe('provider-ref-1')
        ->and($event->payload)->toMatchArray([
            'provider' => 'netbank',
            'provider_reference' => 'provider-ref-1',
            'raw_status' => 'P01',
            'status' => 'pending',
            'received_payload' => [
                'status_code' => 'P01',
                'message' => 'Queued for processing',
            ],
        ])
        ->and($event->metadata)->toMatchArray([
            'source' => 'provider_callback',
            'integration' => 'provider.callback',
            'channel' => 'webhook',
        ]);
});

it('records provider callbacks as journal facts', function () {
    $entry = app(ProviderCallbackJournalRecorder::class)->record(providerCallback());

    expect($entry->reference_number)->toBe('ERN-2026-000000001')
        ->and($entry->event_type)->toBe('provider.callback.received')
        ->and($entry->actor_type)->toBe('provider')
        ->and($entry->execution_id)->toBe('exec-1')
        ->and($entry->references['provider_reference'])->toBe('provider-ref-1')
        ->and($entry->payload['raw_status'])->toBe('P01')
        ->and($entry->metadata['domain'])->toBe('provider')
        ->and($entry->metadata['transformer'])->toBe(ProviderCallbackJournalTransformer::class);
});

it('preserves raw failed provider callback state without deciding settlement outcome', function () {
    $entry = app(ProviderCallbackJournalRecorder::class)->record(providerCallback([
        'raw_status' => 'F99',
        'received_payload' => [
            'status_code' => 'F99',
            'provider_message' => 'Rejected by bank',
        ],
        'payload' => [
            'status' => 'failed',
            'failure_code' => 'BANK_REJECTED',
        ],
    ]));

    expect($entry->payload)->toMatchArray([
        'raw_status' => 'F99',
        'status' => 'failed',
        'failure_code' => 'BANK_REJECTED',
        'received_payload' => [
            'status_code' => 'F99',
            'provider_message' => 'Rejected by bank',
        ],
    ])
        ->and($entry->metadata)->not->toHaveKey('settlement_decision')
        ->and($entry->metadata)->not->toHaveKey('reconciliation_decision')
        ->and($entry->metadata)->not->toHaveKey('next_action');
});

it('makes provider callbacks retrievable by execution and provider references', function () {
    app(ProviderCallbackJournalRecorder::class)->record(providerCallback());

    $byExecution = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'execution_id' => 'exec-1',
    ]));
    $byProvider = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'causation_id' => 'provider-ref-1',
    ]));

    expect($byExecution->total)->toBe(1)
        ->and($byProvider->total)->toBe(1)
        ->and($byProvider->entries->first()?->event_type)->toBe('provider.callback.received');
});

it('does not mutate supplied provider callback data while recording', function () {
    $callback = providerCallback();
    $before = $callback->payload;

    app(ProviderCallbackJournalRecorder::class)->record($callback);

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($callback->payload)->toBe($before);
});
