<?php

namespace LBHurtado\XJournal\Transformers;

use LBHurtado\XJournal\Contracts\JournalEventTransformerContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\JournalEventData;

class OperatorActionJournalTransformer implements JournalEventTransformerContract
{
    public function supports(JournalEventData $event): bool
    {
        return str_starts_with($event->eventType, 'operator.');
    }

    public function transform(JournalEventData $event): ExecutionJournalEntryData
    {
        return new ExecutionJournalEntryData(
            eventType: $event->eventType,
            occurredAt: $event->occurredAt,
            actor: $event->actor,
            subject: $event->subject,
            references: $event->references,
            payload: $event->payload,
            money: $event->money,
            metadata: array_merge($event->metadata, [
                'domain' => 'operator',
                'transformer' => static::class,
            ]),
        );
    }
}
