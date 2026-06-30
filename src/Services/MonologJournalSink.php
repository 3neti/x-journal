<?php

namespace LBHurtado\XJournal\Services;

use Illuminate\Support\Facades\Log;
use LBHurtado\XJournal\Contracts\SecondaryJournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use Psr\Log\LoggerInterface;

class MonologJournalSink implements SecondaryJournalSinkContract
{
    public function __construct(
        protected string $channel = 'default',
        protected ?LoggerInterface $logger = null,
        protected string $message = 'execution.journal.recorded',
    ) {}

    public function recordProjection(ExecutionJournalEntry $entry, ExecutionJournalEntryData $data): void
    {
        $logger = $this->logger ??= Log::channel($this->channel);

        $logger->info($this->message, [
            'ern' => $entry->reference_number,
            'event_type' => $entry->event_type,
            'occurred_at' => optional($entry->occurred_at)->toJSON(),
            'actor_type' => $entry->actor_type,
            'actor_id' => $entry->actor_id,
            'subject_type' => $entry->subject_type,
            'subject_id' => $entry->subject_id,
            'amount' => $entry->money['amount'] ?? null,
            'currency' => $entry->money['currency'] ?? null,
            'correlation_id' => $entry->correlation_id,
            'causation_id' => $entry->causation_id,
            'execution_id' => $entry->execution_id,
            'metadata' => $entry->metadata,
        ]);
    }
}
