<?php

namespace LBHurtado\XJournal\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class OperatorActionData extends Data
{
    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventType,
        public CarbonInterface $occurredAt,
        public ExecutionActorData $actor,
        public ExecutionSubjectData $subject,
        public ExecutionReferenceData $references,
        public array $action,
        public array $context = [],
        public array $payload = [],
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eventType: self::stringValue($data['event_type'] ?? 'operator.action.recorded', 'operator.action.recorded'),
            occurredAt: self::dateValue($data['occurred_at'] ?? null),
            actor: ExecutionActorData::fromArray(self::arrayValue($data['actor'] ?? [
                'type' => 'operator',
                'name' => 'Operator',
            ])),
            subject: ExecutionSubjectData::fromArray(self::arrayValue($data['subject'] ?? [])),
            references: ExecutionReferenceData::fromArray(self::arrayValue($data['references'] ?? [])),
            action: self::arrayValue($data['action'] ?? []),
            context: self::arrayValue($data['context'] ?? []),
            payload: self::arrayValue($data['payload'] ?? []),
            metadata: array_replace([
                'source' => 'operator',
                'integration' => 'operator.action',
            ], self::arrayValue($data['metadata'] ?? [])),
        );
    }

    public function toJournalEvent(): JournalEventData
    {
        return new JournalEventData(
            eventType: $this->eventType,
            occurredAt: $this->occurredAt,
            actor: $this->actor,
            subject: $this->subject,
            references: $this->references,
            payload: $this->eventPayload(),
            metadata: $this->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function eventPayload(): array
    {
        return array_replace([
            'action' => $this->action,
            'context' => $this->context,
        ], $this->payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    protected static function dateValue(mixed $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value);
        }

        return CarbonImmutable::now();
    }

    protected static function stringValue(mixed $value, string $fallback): string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return $fallback;
    }
}
