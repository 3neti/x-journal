<?php

namespace LBHurtado\XJournal\Data;

use Carbon\CarbonInterface;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use Spatie\LaravelData\Data;

final class CockpitJournalEntryData extends Data
{
    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $references
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $referenceNumber,
        public string $eventType,
        public CarbonInterface $occurredAt,
        public array $actor,
        public array $subject,
        public array $references,
        public array $payload,
        public array $metadata,
        public string $visibilityReason,
    ) {}

    public static function fromEntry(ExecutionJournalEntry $entry, JournalAccessDecisionData $decision): self
    {
        return new self(
            referenceNumber: $entry->reference_number,
            eventType: $entry->event_type,
            occurredAt: $entry->occurred_at,
            actor: $entry->actor ?? [],
            subject: $entry->subject ?? [],
            references: $entry->references ?? [],
            payload: $entry->payload ?? [],
            metadata: $entry->metadata ?? [],
            visibilityReason: $decision->reason,
        );
    }

    public static function fromEntryWithProfile(
        ExecutionJournalEntry $entry,
        JournalAccessDecisionData $decision,
        JournalVisibilityProfileData $profile,
    ): self {
        return new self(
            referenceNumber: $entry->reference_number,
            eventType: $entry->event_type,
            occurredAt: $entry->occurred_at,
            actor: $profile->projectActor($entry->actor ?? []),
            subject: $profile->projectSubject($entry->subject ?? []),
            references: $profile->projectReferences($entry->references ?? []),
            payload: $profile->projectPayload($entry->payload ?? []),
            metadata: $profile->projectMetadata($entry->metadata ?? []),
            visibilityReason: $decision->reason,
        );
    }

    /**
     * @return array{
     *     reference_number: string,
     *     event_type: string,
     *     occurred_at: string,
     *     actor: array<string, mixed>,
     *     subject: array<string, mixed>,
     *     references: array<string, mixed>,
     *     payload: array<string, mixed>,
     *     metadata: array<string, mixed>,
     *     visibility_reason: string
     * }
     */
    public function toArray(): array
    {
        return [
            'reference_number' => $this->referenceNumber,
            'event_type' => $this->eventType,
            'occurred_at' => $this->occurredAt->toISOString(),
            'actor' => $this->actor,
            'subject' => $this->subject,
            'references' => $this->references,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
            'visibility_reason' => $this->visibilityReason,
        ];
    }
}
