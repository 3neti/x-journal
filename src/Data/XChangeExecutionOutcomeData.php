<?php

namespace LBHurtado\XJournal\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class XChangeExecutionOutcomeData extends Data
{
    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventType,
        public CarbonInterface $occurredAt,
        public ExecutionActorData $actor,
        public ExecutionSubjectData $subject,
        public ExecutionReferenceData $references,
        public array $result,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $result = self::arrayValue($data['result'] ?? []);
        $references = ExecutionReferenceData::fromArray(self::arrayValue($data['references'] ?? []));

        return new self(
            eventType: self::stringValue($data['event_type'] ?? 'execution.result.recorded', 'execution.result.recorded'),
            occurredAt: self::dateValue($data['occurred_at'] ?? null),
            actor: ExecutionActorData::fromArray(self::arrayValue($data['actor'] ?? [
                'type' => 'system',
                'name' => 'x-change',
            ])),
            subject: ExecutionSubjectData::fromArray(self::arrayValue($data['subject'] ?? [])),
            references: new ExecutionReferenceData(
                correlationId: $references->correlationId,
                causationId: $references->causationId,
                executionId: $references->executionId ?? self::nullableString($result['execution_id'] ?? null),
                providerReference: $references->providerReference,
                externalReference: $references->externalReference,
                metadata: $references->metadata,
            ),
            result: $result,
            metadata: array_replace([
                'source' => 'x-change',
                'integration' => 'x-change.execution',
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
            payload: $this->resultPayload(),
            metadata: $this->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function resultPayload(): array
    {
        return [
            'execution_id' => $this->nullableString($this->result['execution_id'] ?? null),
            'successful' => (bool) ($this->result['successful'] ?? false),
            'status' => self::stringValue($this->result['status'] ?? 'unknown', 'unknown'),
            'driver' => self::stringValue($this->result['driver'] ?? 'unknown', 'unknown'),
            'events' => self::arrayValue($this->result['events'] ?? []),
            'failure' => self::nullableString($this->result['failure'] ?? null),
            'provider_references' => self::arrayValue($this->result['provider_references'] ?? []),
            'reconciliation' => self::arrayValue($this->result['reconciliation'] ?? []),
            'children' => self::arrayValue($this->result['children'] ?? []),
            'metadata' => self::arrayValue($this->result['metadata'] ?? []),
        ];
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

    protected static function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }

    protected static function stringValue(mixed $value, string $fallback): string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return $fallback;
    }
}
