<?php

use LBHurtado\XJournal\Data\JournalRetrievalQueryData;
use LBHurtado\XJournal\Data\OperatorActionData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use LBHurtado\XJournal\Services\OperatorActionJournalRecorder;
use LBHurtado\XJournal\Transformers\OperatorActionJournalTransformer;

function operatorAction(array $overrides = []): OperatorActionData
{
    return OperatorActionData::fromArray(array_replace([
        'event_type' => 'operator.action.recorded',
        'occurred_at' => '2026-06-29T10:15:00Z',
        'actor' => [
            'id' => 'operator-1',
            'type' => 'operator',
            'name' => 'Ops User',
        ],
        'subject' => [
            'id' => 'voucher-1',
            'type' => 'voucher',
            'display' => 'Voucher #1',
        ],
        'references' => [
            'correlation_id' => 'operator-corr-1',
            'causation_id' => 'manual-review-1',
            'execution_id' => 'exec-1',
            'provider_reference' => 'provider-ref-1',
            'external_reference' => 'case-1',
        ],
        'action' => [
            'key' => 'approve_manual_review',
            'label' => 'Approve manual review',
            'target_type' => 'claim',
            'target_id' => 'claim-1',
        ],
        'context' => [
            'reason' => 'documents verified',
            'ip_address' => '127.0.0.1',
        ],
        'payload' => [
            'requested_transition' => 'approved',
        ],
        'metadata' => [
            'surface' => 'cockpit',
        ],
    ], $overrides));
}

it('normalizes operator action payloads without performing the action', function () {
    $event = operatorAction();
    $journalEvent = $event->toJournalEvent();

    expect($journalEvent->eventType)->toBe('operator.action.recorded')
        ->and($journalEvent->actor->type)->toBe('operator')
        ->and($journalEvent->references->executionId)->toBe('exec-1')
        ->and($journalEvent->references->providerReference)->toBe('provider-ref-1')
        ->and($journalEvent->payload)->toMatchArray([
            'action' => [
                'key' => 'approve_manual_review',
                'label' => 'Approve manual review',
                'target_type' => 'claim',
                'target_id' => 'claim-1',
            ],
            'context' => [
                'reason' => 'documents verified',
                'ip_address' => '127.0.0.1',
            ],
            'requested_transition' => 'approved',
        ])
        ->and($journalEvent->metadata)->toMatchArray([
            'source' => 'operator',
            'integration' => 'operator.action',
            'surface' => 'cockpit',
        ]);
});

it('records operator actions as audit facts', function () {
    $entry = app(OperatorActionJournalRecorder::class)->record(operatorAction());

    expect($entry->reference_number)->toBe('ERN-2026-000000001')
        ->and($entry->event_type)->toBe('operator.action.recorded')
        ->and($entry->actor_type)->toBe('operator')
        ->and($entry->subject_type)->toBe('voucher')
        ->and($entry->execution_id)->toBe('exec-1')
        ->and($entry->references['provider_reference'])->toBe('provider-ref-1')
        ->and($entry->payload['action']['key'])->toBe('approve_manual_review')
        ->and($entry->payload['context']['reason'])->toBe('documents verified')
        ->and($entry->metadata['domain'])->toBe('operator')
        ->and($entry->metadata['transformer'])->toBe(OperatorActionJournalTransformer::class);
});

it('records blocked or denied operator actions without performing workflow changes', function () {
    $entry = app(OperatorActionJournalRecorder::class)->record(operatorAction([
        'event_type' => 'operator.action.denied',
        'payload' => [
            'result' => 'denied',
            'blocked_reason' => 'missing_permission',
        ],
    ]));

    expect($entry->event_type)->toBe('operator.action.denied')
        ->and($entry->payload)->toMatchArray([
            'action' => [
                'key' => 'approve_manual_review',
                'label' => 'Approve manual review',
                'target_type' => 'claim',
                'target_id' => 'claim-1',
            ],
            'context' => [
                'reason' => 'documents verified',
                'ip_address' => '127.0.0.1',
            ],
            'result' => 'denied',
            'blocked_reason' => 'missing_permission',
        ])
        ->and($entry->metadata)->not->toHaveKey('workflow_mutated')
        ->and($entry->metadata)->not->toHaveKey('execution_performed')
        ->and($entry->metadata)->not->toHaveKey('money_moved')
        ->and($entry->metadata)->not->toHaveKey('next_action_completed');
});

it('makes operator actions retrievable by execution and causation references', function () {
    app(OperatorActionJournalRecorder::class)->record(operatorAction());

    $byExecution = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'execution_id' => 'exec-1',
    ]));
    $byCausation = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'causation_id' => 'manual-review-1',
    ]));

    expect($byExecution->total)->toBe(1)
        ->and($byCausation->total)->toBe(1)
        ->and($byCausation->entries->first()?->event_type)->toBe('operator.action.recorded');
});

it('does not mutate supplied operator action data while recording', function () {
    $event = operatorAction();
    $before = $event->eventPayload();

    app(OperatorActionJournalRecorder::class)->record($event);

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($event->eventPayload())->toBe($before);
});
