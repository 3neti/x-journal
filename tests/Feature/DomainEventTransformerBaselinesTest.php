<?php

use LBHurtado\XJournal\Data\JournalEventData;
use LBHurtado\XJournal\Services\JournalEventRecorder;
use LBHurtado\XJournal\Services\JournalEventTransformerRegistry;
use LBHurtado\XJournal\Transformers\ClaimLifecycleJournalTransformer;
use LBHurtado\XJournal\Transformers\ProviderCallbackJournalTransformer;
use LBHurtado\XJournal\Transformers\ReconciliationJournalTransformer;

function domainJournalEvent(string $eventType, array $payload = []): JournalEventData
{
    return JournalEventData::fromArray([
        'event_type' => $eventType,
        'occurred_at' => '2026-06-29T10:15:00Z',
        'actor' => [
            'id' => 321,
            'type' => 'operator',
            'name' => 'Ops User',
        ],
        'subject' => [
            'id' => 'subject-1',
            'type' => 'settlement_contract',
            'display' => 'Settlement Contract #1',
        ],
        'references' => [
            'correlation_id' => 'corr-domain-1',
            'causation_id' => 'cause-domain-1',
            'execution_id' => 'exec-domain-1',
            'provider_reference' => 'provider-domain-1',
            'external_reference' => 'external-domain-1',
        ],
        'payload' => $payload,
        'metadata' => [
            'source' => 'domain-test',
        ],
    ]);
}

it('transforms claim lifecycle events without deciding claim outcomes', function () {
    $event = domainJournalEvent('claim.lifecycle.submitted', [
        'claim_state' => 'submitted',
        'requires_manual_review' => true,
    ]);

    $entry = app(JournalEventTransformerRegistry::class)->transform($event);

    expect($entry->eventType)->toBe('claim.lifecycle.submitted')
        ->and($entry->payload)->toBe([
            'claim_state' => 'submitted',
            'requires_manual_review' => true,
        ])
        ->and($entry->metadata['domain'])->toBe('claim')
        ->and($entry->metadata['transformer'])->toBe(ClaimLifecycleJournalTransformer::class);
});

it('transforms provider callback events without interpreting provider success', function () {
    $event = domainJournalEvent('provider.callback.received', [
        'provider' => 'netbank',
        'status' => 'pending',
        'raw_status' => 'P01',
    ]);

    $entry = app(JournalEventTransformerRegistry::class)->transform($event);

    expect($entry->eventType)->toBe('provider.callback.received')
        ->and($entry->payload)->toBe([
            'provider' => 'netbank',
            'status' => 'pending',
            'raw_status' => 'P01',
        ])
        ->and($entry->metadata['domain'])->toBe('provider')
        ->and($entry->metadata['transformer'])->toBe(ProviderCallbackJournalTransformer::class);
});

it('transforms reconciliation events without resolving discrepancies', function () {
    $event = domainJournalEvent('reconciliation.discrepancy.detected', [
        'expected_minor_amount' => 10000,
        'actual_minor_amount' => 9500,
        'currency' => 'PHP',
    ]);

    $entry = app(JournalEventTransformerRegistry::class)->transform($event);

    expect($entry->eventType)->toBe('reconciliation.discrepancy.detected')
        ->and($entry->payload)->toBe([
            'expected_minor_amount' => 10000,
            'actual_minor_amount' => 9500,
            'currency' => 'PHP',
        ])
        ->and($entry->metadata['domain'])->toBe('reconciliation')
        ->and($entry->metadata['transformer'])->toBe(ReconciliationJournalTransformer::class);
});

it('records domain transformed events through the event recorder', function (string $eventType, string $domain) {
    $entry = app(JournalEventRecorder::class)->record(
        domainJournalEvent($eventType, ['status' => 'observed'])
    );

    expect($entry->reference_number)->toBe('ERN-2026-000000001')
        ->and($entry->event_type)->toBe($eventType)
        ->and($entry->correlation_id)->toBe('corr-domain-1')
        ->and($entry->payload)->toBe(['status' => 'observed'])
        ->and($entry->metadata['domain'])->toBe($domain);
})->with([
    'claim' => ['claim.lifecycle.started', 'claim'],
    'provider' => ['provider.callback.received', 'provider'],
    'reconciliation' => ['reconciliation.match.confirmed', 'reconciliation'],
]);
