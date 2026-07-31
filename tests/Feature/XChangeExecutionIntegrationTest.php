<?php

use LBHurtado\XJournal\Data\JournalRetrievalQueryData;
use LBHurtado\XJournal\Data\XChangeExecutionOutcomeData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use LBHurtado\XJournal\Services\XChangeExecutionJournalRecorder;
use LBHurtado\XJournal\Transformers\ExecutionResultJournalTransformer;

function xChangeExecutionOutcome(array $overrides = []): XChangeExecutionOutcomeData
{
    return XChangeExecutionOutcomeData::fromArray(array_replace_recursive([
        'occurred_at' => '2026-06-29T10:15:00Z',
        'actor' => [
            'id' => 'x-change',
            'type' => 'system',
            'name' => 'x-change',
        ],
        'subject' => [
            'id' => 'voucher-1',
            'type' => 'voucher',
            'display' => 'Voucher #1',
        ],
        'references' => [
            'correlation_id' => 'claim-submit-1',
            'causation_id' => 'claim-approved-1',
            'provider_reference' => 'provider-1',
        ],
        'result' => [
            'execution_id' => 'exec-1',
            'successful' => true,
            'status' => 'succeeded',
            'driver' => 'default',
            'events' => ['voucher.redeemed'],
            'provider_references' => ['provider-1'],
            'reconciliation' => ['state' => 'pending'],
            'children' => [],
            'metadata' => ['voucher_id' => 1],
        ],
        'metadata' => [
            'source' => 'x-change',
            'workflow' => 'claim-submit',
        ],
    ], $overrides));
}

it('normalizes x-change execution outcomes without depending on voucher classes', function () {
    $outcome = xChangeExecutionOutcome();
    $event = $outcome->toJournalEvent();

    expect($event->eventType)->toBe('execution.result.recorded')
        ->and($event->references->executionId)->toBe('exec-1')
        ->and($event->references->correlationId)->toBe('claim-submit-1')
        ->and($event->payload)->toMatchArray([
            'execution_id' => 'exec-1',
            'successful' => true,
            'status' => 'succeeded',
            'driver' => 'default',
            'events' => ['voucher.redeemed'],
        ])
        ->and($event->metadata)->toMatchArray([
            'source' => 'x-change',
            'integration' => 'x-change.execution',
            'workflow' => 'claim-submit',
        ]);
});

it('records successful x-change execution outcomes as journal facts', function () {
    $entry = app(XChangeExecutionJournalRecorder::class)->record(xChangeExecutionOutcome());

    expect($entry->reference_number)->toBe('ERN-2026-000000001')
        ->and($entry->event_type)->toBe('execution.result.recorded')
        ->and($entry->actor_type)->toBe('system')
        ->and($entry->subject_type)->toBe('voucher')
        ->and($entry->execution_id)->toBe('exec-1')
        ->and($entry->payload['status'])->toBe('succeeded')
        ->and($entry->payload['driver'])->toBe('default')
        ->and($entry->metadata['transformer'])->toBe(ExecutionResultJournalTransformer::class);
});

it('records failed x-change execution outcomes without deciding recovery actions', function () {
    $entry = app(XChangeExecutionJournalRecorder::class)->record(xChangeExecutionOutcome([
        'result' => [
            'successful' => false,
            'status' => 'failed',
            'failure' => 'provider_timeout',
            'events' => ['provider.timeout'],
        ],
    ]));

    expect($entry->payload)->toMatchArray([
        'successful' => false,
        'status' => 'failed',
        'failure' => 'provider_timeout',
        'events' => ['provider.timeout'],
    ])
        ->and($entry->metadata)->not->toHaveKey('next_action')
        ->and($entry->metadata)->not->toHaveKey('recovery_decision');
});

it('uses execution id from explicit references when present', function () {
    $entry = app(XChangeExecutionJournalRecorder::class)->record(xChangeExecutionOutcome([
        'references' => [
            'execution_id' => 'explicit-exec-1',
        ],
        'result' => [
            'execution_id' => 'result-exec-1',
        ],
    ]));

    expect($entry->execution_id)->toBe('explicit-exec-1')
        ->and($entry->payload['execution_id'])->toBe('result-exec-1');
});

it('makes recorded execution outcomes retrievable by execution id', function () {
    app(XChangeExecutionJournalRecorder::class)->record(xChangeExecutionOutcome());

    $result = app(JournalEntryRetriever::class)->search(
        JournalRetrievalQueryData::fromArray([
            'execution_id' => 'exec-1',
        ])
    );

    expect($result->total)->toBe(1)
        ->and($result->entries->first()?->event_type)->toBe('execution.result.recorded');
});

it('does not mutate supplied execution outcome data while recording', function () {
    $outcome = xChangeExecutionOutcome();
    $before = $outcome->resultPayload();

    app(XChangeExecutionJournalRecorder::class)->record($outcome);

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($outcome->resultPayload())->toBe($before);
});
