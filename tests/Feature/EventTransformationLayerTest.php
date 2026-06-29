<?php

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Contracts\JournalEventTransformerContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\JournalEventData;
use LBHurtado\XJournal\Exceptions\JournalEventTransformerNotFoundException;
use LBHurtado\XJournal\Services\JournalEventRecorder;
use LBHurtado\XJournal\Services\JournalEventTransformerRegistry;
use LBHurtado\XJournal\Transformers\ExecutionResultJournalTransformer;

function executionJournalEvent(array $overrides = []): JournalEventData
{
    return JournalEventData::fromArray(array_replace_recursive([
        'event_type' => 'execution.result.recorded',
        'occurred_at' => '2026-06-29T10:15:00Z',
        'actor' => [
            'id' => 123,
            'type' => 'system',
            'name' => 'Execution Engine',
        ],
        'subject' => [
            'id' => 'voucher-1',
            'type' => 'voucher',
            'display' => 'Voucher #1',
        ],
        'references' => [
            'correlation_id' => 'corr-1',
            'causation_id' => 'cause-1',
            'execution_id' => 'exec-1',
            'provider_reference' => 'provider-1',
        ],
        'money' => [
            'amount' => 100,
            'currency' => 'PHP',
            'minor_amount' => '10000',
        ],
        'payload' => [
            'status' => 'succeeded',
            'driver' => 'default',
            'events' => ['voucher.redeemed'],
        ],
        'metadata' => [
            'source' => 'voucher',
        ],
    ], $overrides));
}

it('normalizes raw execution events before transformation', function () {
    $event = executionJournalEvent();

    expect($event->eventType)->toBe('execution.result.recorded')
        ->and($event->occurredAt->toJSON())->toBe('2026-06-29T10:15:00.000000Z')
        ->and($event->actor->toArray())->toBe([
            'id' => '123',
            'type' => 'system',
            'name' => 'Execution Engine',
            'metadata' => [],
        ])
        ->and($event->money?->toArray())->toBe([
            'amount' => '100',
            'currency' => 'PHP',
            'minor_amount' => 10000,
            'metadata' => [],
        ]);
});

it('transforms execution result events into canonical journal entry data', function () {
    $entry = (new ExecutionResultJournalTransformer)->transform(executionJournalEvent());

    expect($entry)->toBeInstanceOf(ExecutionJournalEntryData::class)
        ->and($entry->eventType)->toBe('execution.result.recorded')
        ->and($entry->references->executionId)->toBe('exec-1')
        ->and($entry->payload)->toBe([
            'status' => 'succeeded',
            'driver' => 'default',
            'events' => ['voucher.redeemed'],
        ])
        ->and($entry->metadata['source'])->toBe('voucher')
        ->and($entry->metadata['transformer'])->toBe(ExecutionResultJournalTransformer::class);
});

it('records transformed execution events through the event recorder', function () {
    $entry = app(JournalEventRecorder::class)->record(executionJournalEvent());

    expect($entry->reference_number)->toBe('ERN-2026-000000001')
        ->and($entry->event_type)->toBe('execution.result.recorded')
        ->and($entry->execution_id)->toBe('exec-1')
        ->and($entry->payload['status'])->toBe('succeeded')
        ->and($entry->metadata['transformer'])->toBe(ExecutionResultJournalTransformer::class);
});

it('does not make business decisions while transforming failed execution results', function () {
    $entry = app(JournalEventRecorder::class)->record(executionJournalEvent([
        'payload' => [
            'status' => 'failed',
            'failure_code' => 'provider_timeout',
        ],
    ]));

    expect($entry->event_type)->toBe('execution.result.recorded')
        ->and($entry->payload['status'])->toBe('failed')
        ->and($entry->payload['failure_code'])->toBe('provider_timeout');
});

it('fails clearly when no transformer supports an event', function () {
    $event = executionJournalEvent([
        'event_type' => 'campaign.distribution.started',
    ]);

    expect(fn () => app(JournalEventTransformerRegistry::class)->transform($event))
        ->toThrow(JournalEventTransformerNotFoundException::class);
});

it('allows package consumers to register event transformers', function () {
    $registry = new JournalEventTransformerRegistry;
    $registry->register(new class implements JournalEventTransformerContract
    {
        public function supports(JournalEventData $event): bool
        {
            return $event->eventType === 'claim.lifecycle.started';
        }

        public function transform(JournalEventData $event): ExecutionJournalEntryData
        {
            return new ExecutionJournalEntryData(
                eventType: $event->eventType,
                occurredAt: CarbonImmutable::parse('2026-06-29T10:15:00Z'),
                actor: new ExecutionActorData(type: 'beneficiary'),
                subject: new ExecutionSubjectData(id: 'claim-1', type: 'claim'),
                references: new ExecutionReferenceData(correlationId: 'corr-claim-1'),
                payload: ['claim_state' => 'started'],
                money: new ExecutionMoneyData(currency: 'PHP'),
            );
        }
    });

    $entry = $registry->transform(JournalEventData::fromArray([
        'event_type' => 'claim.lifecycle.started',
    ]));

    expect($entry->eventType)->toBe('claim.lifecycle.started')
        ->and($entry->payload)->toBe(['claim_state' => 'started'])
        ->and($entry->references->correlationId)->toBe('corr-claim-1');
});
