<?php

use LBHurtado\XJournal\Data\CampaignEventData;
use LBHurtado\XJournal\Data\JournalRetrievalQueryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\CampaignJournalRecorder;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use LBHurtado\XJournal\Transformers\CampaignJournalTransformer;

function campaignEvent(array $overrides = []): CampaignEventData
{
    return CampaignEventData::fromArray(array_replace([
        'event_type' => 'campaign.distribution.planned',
        'occurred_at' => '2026-06-29T10:15:00Z',
        'actor' => [
            'id' => 'campaign-worker',
            'type' => 'system',
            'name' => 'Campaign Worker',
        ],
        'subject' => [
            'id' => 'campaign-1',
            'type' => 'campaign',
            'display' => 'Relief Campaign #1',
        ],
        'references' => [
            'correlation_id' => 'campaign-corr-1',
            'causation_id' => 'program-approval-1',
            'execution_id' => 'exec-1',
            'provider_reference' => 'provider-ref-1',
            'external_reference' => 'distribution-plan-1',
            'metadata' => [
                'voucher_batch_id' => 'voucher-batch-1',
                'claim_id' => 'claim-1',
            ],
        ],
        'campaign' => [
            'id' => 'campaign-1',
            'name' => 'Relief Campaign #1',
            'status' => 'planning',
        ],
        'program' => [
            'id' => 'program-1',
            'name' => 'Emergency Relief',
        ],
        'beneficiary_list' => [
            'id' => 'beneficiary-list-1',
            'count' => 250,
        ],
        'distribution' => [
            'channel' => 'sms',
            'scheduled_for' => '2026-07-01T00:00:00Z',
        ],
        'batch' => [
            'id' => 'voucher-batch-1',
            'planned_count' => 250,
        ],
        'payload' => [
            'planning_window' => 'july-2026',
        ],
        'metadata' => [
            'surface' => 'campaign-console',
        ],
    ], $overrides));
}

it('normalizes campaign event payloads without issuing vouchers', function () {
    $event = campaignEvent();
    $journalEvent = $event->toJournalEvent();

    expect($journalEvent->eventType)->toBe('campaign.distribution.planned')
        ->and($journalEvent->actor->type)->toBe('system')
        ->and($journalEvent->subject->type)->toBe('campaign')
        ->and($journalEvent->references->executionId)->toBe('exec-1')
        ->and($journalEvent->references->metadata['voucher_batch_id'])->toBe('voucher-batch-1')
        ->and($journalEvent->payload)->toMatchArray([
            'campaign' => [
                'id' => 'campaign-1',
                'name' => 'Relief Campaign #1',
                'status' => 'planning',
            ],
            'program' => [
                'id' => 'program-1',
                'name' => 'Emergency Relief',
            ],
            'beneficiary_list' => [
                'id' => 'beneficiary-list-1',
                'count' => 250,
            ],
            'distribution' => [
                'channel' => 'sms',
                'scheduled_for' => '2026-07-01T00:00:00Z',
            ],
            'batch' => [
                'id' => 'voucher-batch-1',
                'planned_count' => 250,
            ],
            'planning_window' => 'july-2026',
        ])
        ->and($journalEvent->metadata)->toMatchArray([
            'source' => 'campaign',
            'integration' => 'campaign',
            'surface' => 'campaign-console',
        ]);
});

it('records campaign events as audit facts', function () {
    $entry = app(CampaignJournalRecorder::class)->record(campaignEvent());

    expect($entry->reference_number)->toBe('ERN-2026-000000001')
        ->and($entry->event_type)->toBe('campaign.distribution.planned')
        ->and($entry->actor_type)->toBe('system')
        ->and($entry->subject_type)->toBe('campaign')
        ->and($entry->subject_id)->toBe('campaign-1')
        ->and($entry->execution_id)->toBe('exec-1')
        ->and($entry->references['metadata']['voucher_batch_id'])->toBe('voucher-batch-1')
        ->and($entry->payload['campaign']['id'])->toBe('campaign-1')
        ->and($entry->payload['batch']['planned_count'])->toBe(250)
        ->and($entry->metadata['domain'])->toBe('campaign')
        ->and($entry->metadata['transformer'])->toBe(CampaignJournalTransformer::class);
});

it('preserves campaign batch facts without deciding issuance or execution', function () {
    $entry = app(CampaignJournalRecorder::class)->record(campaignEvent([
        'event_type' => 'campaign.batch.prepared',
        'payload' => [
            'eligible_beneficiaries' => 240,
            'ineligible_beneficiaries' => 10,
        ],
    ]));

    expect($entry->payload)->toMatchArray([
        'campaign' => [
            'id' => 'campaign-1',
            'name' => 'Relief Campaign #1',
            'status' => 'planning',
        ],
        'batch' => [
            'id' => 'voucher-batch-1',
            'planned_count' => 250,
        ],
        'eligible_beneficiaries' => 240,
        'ineligible_beneficiaries' => 10,
    ])
        ->and($entry->metadata)->not->toHaveKey('vouchers_issued')
        ->and($entry->metadata)->not->toHaveKey('execution_decision')
        ->and($entry->metadata)->not->toHaveKey('campaign_state_mutated')
        ->and($entry->metadata)->not->toHaveKey('distribution_dispatched');
});

it('makes campaign entries retrievable by execution and program references', function () {
    app(CampaignJournalRecorder::class)->record(campaignEvent());

    $byExecution = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'execution_id' => 'exec-1',
    ]));
    $byProgram = app(JournalEntryRetriever::class)->search(JournalRetrievalQueryData::fromArray([
        'causation_id' => 'program-approval-1',
    ]));

    expect($byExecution->total)->toBe(1)
        ->and($byProgram->total)->toBe(1)
        ->and($byProgram->entries->first()?->event_type)->toBe('campaign.distribution.planned');
});

it('does not mutate supplied campaign event data while recording', function () {
    $event = campaignEvent();
    $before = $event->eventPayload();

    app(CampaignJournalRecorder::class)->record($event);

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($event->eventPayload())->toBe($before);
});
